<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Command;

use DateInterval;
use DateTimeImmutable;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintrag;
use OCA\Berichtsheft\Db\LehrjahrZuweisung;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Db\WocheMapper;
use OCA\Berichtsheft\Service\DeckblattService;
use OCA\Berichtsheft\Service\EintragService;
use OCA\Berichtsheft\Service\FileStorageService;
use OCA\Berichtsheft\Service\GesamtExportService;
use OCA\Berichtsheft\Service\NotenService;
use OCP\IDBConnection;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * NUR fuers Testsystem: legt Demo-Azubis mit realistischer FI-SI-Berichtsheft-
 * Historie (Betriebstage + 1 Berufsschultag/Woche inkl. Faechern + Noten),
 * eingereicht+akzeptiert, mit Lehrjahr-Uebergaengen (Noten-Archivierung) an und
 * erzeugt die PDFs (Gesamtnachweis + Noten-Uebersicht). Setzt den Wochenstatus
 * direkt auf AKZEPTIERT (umgeht bewusst die In-App-/Mail-Benachrichtigung der
 * einreichen()/akzeptieren()-Flows, sonst haette der Ausbilder hunderte Mails).
 */
class SeedDemo extends Command {
	public function __construct(
		private IUserManager $userManager,
		private IDBConnection $db,
		private AzubiMapper $azubiMapper,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private WocheMapper $wocheMapper,
		private EintragService $eintragService,
		private NotenService $notenService,
		private GesamtExportService $gesamtExportService,
		private FileStorageService $fileStorageService,
		private DeckblattService $deckblattService,
	) {
		parent::__construct();
	}

	// Betriebstaetigkeiten (FI-SI), werden rotierend/variiert verwendet.
	private const TAETIGKEITEN = [
		'Active-Directory-Benutzerkonten angelegt und Gruppenrichtlinien angepasst',
		'Tickets im Helpdesk-System bearbeitet (Passwort-Resets, Drucker, Software)',
		'Windows-Server 2022 gepatcht und Neustart im Wartungsfenster durchgefuehrt',
		'Backup-Jobs (Veeam) kontrolliert und einen Restore-Test protokolliert',
		'Netzwerk-Switch konfiguriert: VLANs und Portzuordnung dokumentiert',
		'Clients neu aufgesetzt und per Intune/GPO in die Domaene aufgenommen',
		'Monitoring in Zabbix eingerichtet: neue Hosts und Trigger angelegt',
		'Firewall-Regeln (OPNsense) geprueft und eine Freigabe umgesetzt',
		'Virtuelle Maschine unter Proxmox erstellt und Grundinstallation durchgefuehrt',
		'Netzwerkdose gepatcht, Verbindung gemessen und im Patchplan dokumentiert',
		'Rollout eines Software-Updates per Paketverwaltung vorbereitet und getestet',
		'Fehleranalyse an einem Notebook (kein Netzwerk) durchgefuehrt und behoben',
		'DHCP-/DNS-Konfiguration kontrolliert und einen Reservierungseintrag ergaenzt',
		'Dokumentation im Wiki aktualisiert (Netzplan und Serveruebersicht)',
		'NAS-Freigabe eingerichtet und Berechtigungen nach Abteilungen gesetzt',
		'Inventarisierung der Hardware fortgefuehrt und Etiketten vergeben',
		'E-Mail-Postfach migriert und Weiterleitungen eingerichtet',
		'VPN-Zugang fuer einen neuen Mitarbeiter eingerichtet und getestet',
		'Telefonanlage: Nebenstelle konfiguriert und Rufumleitung eingerichtet',
		'USV geprueft, Batterietest ausgewertet und Ergebnis dokumentiert',
	];

	// Kurz-Inhalte fuer Berufsschul-Faecher (rotierend).
	private const SCHUL_THEMEN = [
		'Grundlagen und Uebungen', 'Projektarbeit fortgesetzt', 'Klassenarbeit besprochen',
		'Neues Kapitel begonnen', 'Praktische Aufgaben im Labor', 'Wiederholung zur Pruefung',
		'Fallbeispiel bearbeitet', 'Praesentation vorbereitet',
	];

	protected function configure(): void {
		$this->setName('berichtsheft:seed-demo')
			->setDescription('Legt Demo-Azubis mit realistischer Historie + Noten an und erzeugt die PDFs (nur Testsystem).')
			->addArgument('profil', InputArgument::OPTIONAL, '1 (1 Jahr) | 2 (2 Jahre) | 3 (abgeschlossen) | all', 'all')
			->addOption('ausbilder', null, InputOption::VALUE_REQUIRED, 'Ausbilder-User-ID', 'PeterHein')
			->addOption('loeschen', null, InputOption::VALUE_NONE, 'Vorhandene Demo-Daten dieses Profils vorher entfernen');
	}

	/** @return array<int,array<string,mixed>> */
	private function profile(): array {
		// Startdaten sind Montage (Wochen sind Mo-verankert). jahre = Anzahl
		// Lehrjahre; wochen = Anzahl zu seedender Wochen.
		return [
			1 => ['uid' => 'demo-azubi-lj1', 'vorname' => 'Lena', 'nachname' => 'Sommer', 'email' => 'lena.sommer@example.test', 'start' => '2025-08-04', 'jahre' => 1, 'wochen' => 50, 'beendet' => false],
			2 => ['uid' => 'demo-azubi-lj2', 'vorname' => 'Tom', 'nachname' => 'Berger', 'email' => 'tom.berger@example.test', 'start' => '2024-08-05', 'jahre' => 2, 'wochen' => 100, 'beendet' => false],
			3 => ['uid' => 'demo-azubi-fertig', 'vorname' => 'Jonas', 'nachname' => 'Klein', 'email' => 'jonas.klein@example.test', 'start' => '2023-08-07', 'jahre' => 3, 'wochen' => 156, 'beendet' => true],
		];
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$profilArg = (string)$input->getArgument('profil');
		$ausbilder = (string)$input->getOption('ausbilder');
		if (!$this->userManager->userExists($ausbilder)) {
			$output->writeln("<error>Ausbilder-User '$ausbilder' existiert nicht.</error>");
			return 1;
		}
		$alle = $this->profile();
		$keys = $profilArg === 'all' ? array_keys($alle) : [(int)$profilArg];

		foreach ($keys as $k) {
			if (!isset($alle[$k])) {
				$output->writeln("<error>Unbekanntes Profil: $k</error>");
				return 1;
			}
			$this->seedAzubi($alle[$k], $ausbilder, (bool)$input->getOption('loeschen'), $output);
		}
		$output->writeln('<info>Fertig.</info>');
		return 0;
	}

	private function seedAzubi(array $cfg, string $ausbilder, bool $loeschen, OutputInterface $out): void {
		$uid = (string)$cfg['uid'];
		$out->writeln("=== Profil {$cfg['vorname']} {$cfg['nachname']} ({$uid}) ===");

		if ($loeschen) {
			$this->loescheDemo($uid, $out);
		}

		// 1) Nextcloud-User
		if (!$this->userManager->userExists($uid)) {
			$user = $this->userManager->createUser($uid, bin2hex(random_bytes(12)) . 'Aa1!');
			$out->writeln("  User angelegt: $uid");
		} else {
			$user = $this->userManager->get($uid);
			$out->writeln("  User existiert bereits: $uid");
		}
		$user->setDisplayName($cfg['vorname'] . ' ' . $cfg['nachname']);
		$user->setEMailAddress((string)$cfg['email']);

		// 2) Azubi-Datensatz (falls schon vorhanden: abbrechen, nichts doppeln)
		if ($this->azubiMapper->existsForUserId($uid)) {
			$out->writeln('  <comment>Azubi-Datensatz existiert bereits - uebersprungen (mit --loeschen neu anlegen).</comment>');
			return;
		}
		$now = time();
		$azubi = new Azubi();
		$azubi->setUserId($uid);
		$azubi->setAusbildungsberuf('fisi');
		$azubi->setAusbildungsstart((string)$cfg['start']);
		$azubi->setAusbildungsjahrStartWert(1);
		$azubi->setVerantwortlicherAusbilderUserId($ausbilder);
		$azubi->setAusbildungsabteilung('IT-Systemadministration');
		$azubi->setVorname((string)$cfg['vorname']);
		$azubi->setNachname((string)$cfg['nachname']);
		$azubi->setLastReminderSentOn(null);
		$azubi->setStatus(Azubi::STATUS_AKTIV);
		$azubi->setCreatedAt($now);
		$azubi->setUpdatedAt($now);
		$azubi = $this->azubiMapper->insert($azubi);
		$this->fileStorageService->ensureBerichtsheftOrdnerUndGruppenShare($azubi);
		$this->deckblattService->erzeugen($azubi);

		$start = new DateTimeImmutable((string)$cfg['start']);
		$jahre = (int)$cfg['jahre'];
		$wochenGesamt = (int)$cfg['wochen'];

		// 3) LJ1-Zuweisung
		$vorZuw = $this->zuweisung($azubi->getId(), (string)$cfg['start'], 1, $ausbilder, $now);

		// 4) Lehrjahre + Wochen (interleaved, damit Noten vor der Archivierung existieren)
		for ($lj = 1; $lj <= $jahre; $lj++) {
			$vonWoche = ($lj - 1) * 52;
			$bisWoche = min($lj * 52, $wochenGesamt);
			$out->writeln("  Lehrjahr $lj: Wochen " . ($vonWoche + 1) . "-$bisWoche ...");
			for ($w = $vonWoche; $w < $bisWoche; $w++) {
				$this->seedWoche($azubi, $start->add(new DateInterval('P' . ($w * 7) . 'D')), $w, $ausbilder);
			}
			if ($lj < $jahre) {
				$ljNextAb = $start->add(new DateInterval('P' . ($lj * 364) . 'D'))->format('Y-m-d');
				$neu = $this->zuweisung($azubi->getId(), $ljNextAb, $lj + 1, $ausbilder, $now);
				$this->notenService->archiviereLehrjahrende($azubi, $vorZuw, $ljNextAb);
				$vorZuw = $neu;
			}
		}

		// 5) Noten-Live-Uebersicht (aktuelles Lehrjahr) + Gesamtnachweis-PDF
		$this->notenService->aktualisiereAktuelleUebersicht($azubi);
		$out->writeln('  Noten-Uebersicht aktualisiert.');
		$this->gesamtExportService->erzeugen($azubi);
		$out->writeln('  Gesamtnachweis-PDF erzeugt.');

		// 6) abgeschlossene Ausbildung -> Status beendet
		if ($cfg['beendet']) {
			$azubi->setStatus(Azubi::STATUS_BEENDET);
			$azubi->setUpdatedAt(time());
			$this->azubiMapper->update($azubi);
			$out->writeln('  Azubi auf Status "beendet" gesetzt.');
		}
	}

	private function zuweisung(int $azubiId, string $gueltigAb, int $lehrjahr, string $ausbilder, int $ts): LehrjahrZuweisung {
		$z = new LehrjahrZuweisung();
		$z->setAzubiId($azubiId);
		$z->setGueltigAb($gueltigAb);
		$z->setLehrjahr($lehrjahr);
		$z->setFestgelegtVonUserId($ausbilder);
		$z->setFestgelegtAm($ts);
		return $this->lehrjahrZuweisungMapper->insert($z);
	}

	private function seedWoche(Azubi $azubi, DateTimeImmutable $montag, int $wIdx, string $ausbilder): void {
		$wocheVon = $montag->format('Y-m-d');
		// ~alle 12 Wochen eine Urlaubswoche, sonst normale Woche mit 1 Schultag (Mi).
		$urlaubswoche = ($wIdx % 12) === 8;
		$schultag = 2; // 0=Mo ... 2=Mi

		for ($d = 0; $d < 5; $d++) {
			$datum = $montag->add(new DateInterval('P' . $d . 'D'))->format('Y-m-d');
			if ($urlaubswoche) {
				$this->eintragService->speichereEintrag($azubi, $datum, Eintrag::TAG_TYP_URLAUB, null, null, []);
				continue;
			}
			if ($d === $schultag) {
				$faecher = $this->schulFaecher($azubi, $datum, $wIdx);
				$this->eintragService->speichereEintrag($azubi, $datum, Eintrag::TAG_TYP_BERUFSSCHULE, null, null, $faecher);
			} else {
				$t = self::TAETIGKEITEN[($wIdx * 5 + $d) % count(self::TAETIGKEITEN)];
				$this->eintragService->speichereEintrag($azubi, $datum, Eintrag::TAG_TYP_BETRIEB, $t, 8.0, []);
			}
		}

		// Woche direkt auf AKZEPTIERT setzen (ohne Benachrichtigungen).
		$woche = $this->wocheMapper->findByAzubiAndWocheVon($azubi->getId(), $wocheVon);
		$name = $azubi->getVorname() . ' ' . $azubi->getNachname();
		$bisTs = (new DateTimeImmutable($woche->getWocheBis()))->getTimestamp();
		$woche->setStatus(Woche::STATUS_AKZEPTIERT);
		$woche->setEingereichtVonUserId($azubi->getUserId());
		$woche->setEingereichtVonName($name);
		$woche->setEingereichtAm($bisTs + 18 * 3600);
		$woche->setAkzeptiertVonUserId($ausbilder);
		$woche->setAkzeptiertVonName($this->userManager->get($ausbilder)?->getDisplayName() ?? $ausbilder);
		$woche->setAkzeptiertAm($bisTs + 30 * 3600);
		$this->wocheMapper->update($woche);
	}

	/**
	 * Baut 2-3 Fach-Zeilen fuer einen Schultag; ~jede 3. Woche eine Note.
	 * @return array<array{fachId:int,stunden:float,inhalt?:?string,noteArt?:?string,note?:?int}>
	 */
	private function schulFaecher(Azubi $azubi, string $datum, int $wIdx): array {
		$verfuegbar = $this->eintragService->getVerfuegbareFaecher($azubi, $datum);
		if (count($verfuegbar) === 0) {
			return [];
		}
		$anzahl = min(3, count($verfuegbar));
		$offset = $wIdx % count($verfuegbar);
		$noteArten = [FachEintrag::NOTE_ART_SCHRIFTLICH, FachEintrag::NOTE_ART_MUENDLICH, FachEintrag::NOTE_ART_STEHGREIF];
		$noteWerte = [2, 3, 3, 2, 4, 1, 3, 2];
		$zeilen = [];
		for ($i = 0; $i < $anzahl; $i++) {
			/** @var Fach $fach */
			$fach = $verfuegbar[($offset + $i) % count($verfuegbar)];
			$zeile = [
				'fachId' => $fach->getId(),
				'stunden' => $i === 0 ? 4.0 : 2.0,
				'inhalt' => self::SCHUL_THEMEN[($wIdx + $i) % count(self::SCHUL_THEMEN)],
			];
			// ~jede 3. Woche genau eine Note (auf dem ersten Fach), gestreut.
			if ($i === 0 && ($wIdx % 3) === 0) {
				$zeile['noteArt'] = $noteArten[intdiv($wIdx, 3) % 3];
				$zeile['note'] = $noteWerte[($wIdx) % count($noteWerte)];
			}
			$zeilen[] = $zeile;
		}
		return $zeilen;
	}

	private function loescheDemo(string $uid, OutputInterface $out): void {
		$azubiId = null;
		try {
			$azubiId = $this->azubiMapper->findByUserId($uid)->getId();
		} catch (\OCP\AppFramework\Db\DoesNotExistException) {
			// kein Datensatz vorhanden
		}
		if ($azubiId !== null) {
			// Fach-Eintraege ueber die Eintraege des Azubi entfernen, dann der Rest.
			$this->db->executeStatement(
				'DELETE fe FROM `*PREFIX*bh_fach_eintrag` fe JOIN `*PREFIX*bh_eintrag` e ON fe.eintrag_id = e.id WHERE e.azubi_id = ?',
				[$azubiId]
			);
			foreach (['bh_eintrag', 'bh_woche', 'bh_lehrjahr_zuweisung', 'bh_export'] as $t) {
				$this->db->executeStatement('DELETE FROM `*PREFIX*' . $t . '` WHERE `azubi_id` = ?', [$azubiId]);
			}
			$this->db->executeStatement('DELETE FROM `*PREFIX*bh_azubi` WHERE `id` = ?', [$azubiId]);
			$out->writeln("  Vorhandene Demo-Daten (azubi_id=$azubiId) entfernt.");
		}
		if ($this->userManager->userExists($uid)) {
			$this->userManager->get($uid)->delete();
			$out->writeln("  User $uid geloescht.");
		}
	}
}
