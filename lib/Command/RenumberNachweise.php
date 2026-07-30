<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Command;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Export;
use OCA\Berichtsheft\Db\ExportMapper;
use OCA\Berichtsheft\Db\WocheMapper;
use OCA\Berichtsheft\Service\EintragService;
use OCA\Berichtsheft\Service\FileStorageService;
use OCA\Berichtsheft\Service\GesamtExportService;
use OCA\Berichtsheft\Service\PdfExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reparatur-Kommando fuer den Nachweis-Nummerierungs-Bug: bh_woche.nachweis_nr
 * wurde bisher strikt nach Anlage-Reihenfolge vergeben (letzte Nummer + 1)
 * statt nach kalendarischem Abstand zur Ausbildungsstart-Woche - fuellte ein
 * Azubi z.B. zuerst eine spaetere Kalenderwoche aus, bekam sie faelschlich
 * eine niedrigere Nummer, statt der kalendarisch korrekten (s.
 * EintragService::nachweisNrFuer(), dort seit dem Fix die einzige Quelle
 * fuer neu angelegte Wochen). Dieses Kommando berechnet nachweis_nr fuer
 * ALLE bereits bestehenden bh_woche-Zeilen aller Azubis nach derselben
 * Formel neu und regeneriert anschliessend jedes bereits erzeugte PDF
 * (4-Wochen-Exporte + IHK-Gesamtnachweis), da dort die (ggf. falsche)
 * Nummer schon fest ins gerenderte PDF eingebrannt war.
 *
 * Zwei Phasen pro Azubi gegen den UNIQUE-Index (azubi_id, nachweis_nr):
 * erst alle betroffenen Zeilen auf einen hohen, garantiert kollisions-
 * freien Platzhalter setzen, danach jede auf ihre tatsaechlich korrekte
 * Nummer - sonst koennte ein Zwischenschritt versuchen, eine Nummer zu
 * vergeben, die eine andere (noch nicht aktualisierte) Zeile desselben
 * Azubis gerade noch traegt.
 */
class RenumberNachweise extends Command {
	private const PLATZHALTER_OFFSET = 1000000;

	public function __construct(
		private IDBConnection $db,
		private AzubiMapper $azubiMapper,
		private WocheMapper $wocheMapper,
		private ExportMapper $exportMapper,
		private PdfExportService $pdfExportService,
		private GesamtExportService $gesamtExportService,
		private FileStorageService $fileStorageService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('berichtsheft:renumber-nachweise')
			->setDescription('Berechnet nachweis_nr aller Wochen aller Azubis neu (kalendarischer Abstand zum Ausbildungsstart statt Anlage-Reihenfolge) und regeneriert betroffene PDFs.')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Nur anzeigen, was sich aendern wuerde, nichts schreiben.')
			->addOption('ohne-pdf', null, InputOption::VALUE_NONE, 'PDFs NICHT neu erzeugen, nur die Nummern korrigieren.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		$ohnePdf = (bool)$input->getOption('ohne-pdf');

		$geaenderteAzubiIds = [];
		$gesamtGeaendert = 0;

		foreach ($this->azubiMapper->findAll() as $azubi) {
			$wochen = $this->wocheMapper->findAllByAzubi($azubi->getId());
			if (count($wochen) === 0) {
				continue;
			}

			$aenderungen = [];
			foreach ($wochen as $woche) {
				$korrekt = EintragService::nachweisNrFuer($azubi, $woche->getWocheVon());
				if ($korrekt !== $woche->getNachweisNr()) {
					$aenderungen[] = [$woche, $woche->getNachweisNr(), $korrekt];
				}
			}

			if (count($aenderungen) === 0) {
				continue;
			}

			$name = trim(($azubi->getVorname() ?? '') . ' ' . ($azubi->getNachname() ?? '')) ?: $azubi->getUserId();
			$output->writeln("<info>$name (Azubi #{$azubi->getId()}): " . count($aenderungen) . ' Woche(n) betroffen</info>');
			foreach ($aenderungen as [$woche, $alt, $neu]) {
				$output->writeln("  {$woche->getWocheVon()}: Nachweis $alt -> $neu");
			}

			if ($dryRun) {
				// Nur im Dry-Run zaehlen "wuerde geaendert werden" - im echten
				// Lauf erst NACH erfolgreichem Commit (s.u.), sonst wuerden bei
				// einem Azubi mit zurueckgerolltem Fehler (z.B. Datenproblem wie
				// "Wochen liegen vor dem Ausbildungsstart") dessen Aenderungen
				// faelschlich als "korrigiert" mitgezaehlt, obwohl nichts
				// geschrieben wurde.
				$gesamtGeaendert += count($aenderungen);
				continue;
			}

			$this->db->beginTransaction();
			try {
				// Phase 1: Platzhalter (kollisionsfrei mit jeder bestehenden
				// Nummer dieses Azubis, da weit ausserhalb des realistischen Bereichs).
				foreach ($aenderungen as [$woche, $alt]) {
					$woche->setNachweisNr($alt + self::PLATZHALTER_OFFSET);
					$this->wocheMapper->update($woche);
				}
				// Phase 2: echte, korrekte Nummern setzen.
				foreach ($aenderungen as [$woche, , $neu]) {
					$woche->setNachweisNr($neu);
					$this->wocheMapper->update($woche);
				}
				$this->db->commit();
			} catch (\Throwable $e) {
				$this->db->rollBack();
				$output->writeln("<error>Fehler bei Azubi #{$azubi->getId()}, Aenderungen zurueckgerollt: {$e->getMessage()}</error>");
				continue;
			}

			$gesamtGeaendert += count($aenderungen);
			$geaenderteAzubiIds[] = $azubi->getId();
		}

		if ($dryRun) {
			$output->writeln("<comment>Dry-Run: $gesamtGeaendert Woche(n) betroffen, nichts geschrieben.</comment>");
			return 0;
		}

		$output->writeln("<info>$gesamtGeaendert Woche(n) korrigiert.</info>");

		if ($ohnePdf || count($geaenderteAzubiIds) === 0) {
			return 0;
		}

		$output->writeln('<info>Regeneriere betroffene PDFs...</info>');
		foreach (array_unique($geaenderteAzubiIds) as $azubiId) {
			try {
				$azubi = $this->azubiMapper->find($azubiId);
			} catch (DoesNotExistException) {
				continue;
			}

			// 4-Wochen-Exporte: alle bereits gerenderten (nicht nur noch
			// wartende - ExportGeneratorJob erzeugt wartende ohnehin regulaer,
			// sobald faellig, mit dann schon korrekten Nummern).
			foreach ($this->exportMapper->findByAzubi($azubiId) as $export) {
				if ($export->getStatus() !== Export::STATUS_EXPORTIERT) {
					continue;
				}
				$exportWochen = $this->wocheMapper->findByExportId($export->getId());
				if (count($exportWochen) === 0) {
					continue;
				}
				$this->pdfExportService->erzeugeExport($azubi, $export, $exportWochen);
				$output->writeln("  Export #{$export->getExportNr()} (Azubi #$azubiId) neu erzeugt.");
			}

			// IHK-Gesamtnachweis: nur falls schon mind. 1 akzeptierte Woche
			// vorliegt (sonst wirft erzeugen() eine DomainException) - und nur,
			// wenn ueberhaupt schon einer erzeugt wurde (kein PDF ohne
			// vorherigen manuellen Ausbilder-Trigger anlegen).
			if (count($this->wocheMapper->findAkzeptiertByAzubi($azubiId)) > 0
				&& $this->gesamtExportExistiert($azubi)) {
				try {
					$this->gesamtExportService->erzeugen($azubi);
					$output->writeln("  Gesamtnachweis (Azubi #$azubiId) neu erzeugt.");
				} catch (\DomainException $e) {
					$output->writeln("  <comment>Gesamtnachweis (Azubi #$azubiId) uebersprungen: {$e->getMessage()}</comment>");
				}
			}
		}

		return 0;
	}

	/**
	 * GesamtExportService hat keine eigene DB-Zeile/Status - das PDF liegt
	 * nur als Datei im geteilten Ordner. Ohne einen sauberen "existiert
	 * bereits ein Gesamtnachweis"-Check wuerde dieses Kommando fuer JEDEN
	 * Azubi mit akzeptierten Wochen einen neuen Gesamtnachweis erzwingen,
	 * auch fuer Azubis, die noch nie einen angefordert haben - das ist
	 * ausdruecklich ein manueller Ausbilder-Vorgang (s. GesamtExportService),
	 * kein automatischer. Prueft daher direkt per FileStorageService, ob
	 * bereits eine Datei mit dem von GesamtExportService verwendeten
	 * Dateinamen existiert.
	 */
	private function gesamtExportExistiert(Azubi $azubi): bool {
		$dateiname = PdfExportService::azubiDateinameBasis($azubi, $this->userManager) . ' - Gesamtnachweis.pdf';
		try {
			return $this->fileStorageService->dateiExistiert($azubi, $dateiname);
		} catch (\Throwable) {
			// Im Zweifel NICHT automatisch erzeugen - lieber ein fehlendes
			// Update eines (unwahrscheinlichen) Sonderfalls als ein
			// ungewolltes Neuanlegen fuer einen Azubi ohne bisherigen Gesamtnachweis.
			return false;
		}
	}
}
