<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use DateInterval;
use DateTimeImmutable;
use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintrag;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachLehrjahrMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Db\WocheMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager as INotificationManager;

/**
 * Kernlogik der Tageseintraege + Einreichen-Aktion (Plan Abschnitt 2/3).
 * Kapselt: Bearbeitungssperre, Vollstaendigkeitspruefung vor Einreichung,
 * Lehrjahr-Ermittlung (NICHT berechnet, s. LehrjahrZuweisungMapper) +
 * Fach-Filterung, sowie Ausbildungsjahr-Berechnung fuers Deckblatt/PDF.
 */
class EintragService {
	public function __construct(
		private EintragMapper $eintragMapper,
		private FachEintragMapper $fachEintragMapper,
		private FachMapper $fachMapper,
		private FachLehrjahrMapper $fachLehrjahrMapper,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private WocheMapper $wocheMapper,
		private INotificationManager $notificationManager,
		private MailService $mailService,
	) {
	}

	/** Montag der Woche, die $datum enthaelt (ISO-8601, Montag = Wochenstart). */
	public static function wocheVonFuer(string $datum): string {
		$d = new DateTimeImmutable($datum);
		$offset = ((int)$d->format('N')) - 1; // Montag=1 -> 0 Tage zurueck
		return $d->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
	}

	public static function wocheBisFuer(string $wocheVon): string {
		return (new DateTimeImmutable($wocheVon))->add(new DateInterval('P6D'))->format('Y-m-d');
	}

	/**
	 * Lehrjahr zu einem Datum - vom Ausbilder festgelegt, NICHT berechnet
	 * (Plan Abschnitt 2). Wirft, wenn noch keine Zuweisung existiert (sollte
	 * durch die Erstbefuellung bei der Azubi-Aktivierung nicht vorkommen).
	 * @throws DoesNotExistException
	 */
	public function getLehrjahr(Azubi $azubi, string $datum): int {
		$zuweisung = $this->lehrjahrZuweisungMapper->findAktuellFuerAzubi($azubi->getId(), $datum);
		return $zuweisung->getLehrjahr();
	}

	/** Ausbildungsjahr (chronologisch, PDF-Kopf) - Plan Abschnitt 2, Formel. */
	public function getAusbildungsjahr(Azubi $azubi, string $datum): int {
		$start = new DateTimeImmutable($azubi->getAusbildungsstart());
		$ziel = new DateTimeImmutable($datum);
		$tage = $start->diff($ziel)->days ?? 0;
		return $azubi->getAusbildungsjahrStartWert() + intdiv($tage, 365);
	}

	/** Faecher, die im aktuell gueltigen Lehrjahr zur Auswahl stehen. @return Fach[] */
	public function getVerfuegbareFaecher(Azubi $azubi, string $datum): array {
		try {
			$lehrjahr = $this->getLehrjahr($azubi, $datum);
		} catch (DoesNotExistException) {
			return [];
		}
		$fachIds = $this->fachLehrjahrMapper->findFachIdsByLehrjahr($lehrjahr);
		$alle = $this->fachMapper->findAll();
		return array_values(array_filter($alle, static fn (Fach $f) => in_array($f->getId(), $fachIds, true)));
	}

	/**
	 * Liefert die bh_woche-Zeile fuer den angegebenen Wochenbeginn, legt sie
	 * bei Bedarf neu an (status=offen). bh_eintrag hat bewusst keinen FK auf
	 * bh_woche (Plan Abschnitt 2) - Tageseintraege koennen vor der
	 * Woche-Zeile existieren, diese Methode holt/erzeugt sie idempotent.
	 */
	public function getOderErstelleWoche(Azubi $azubi, string $wocheVon): Woche {
		try {
			return $this->wocheMapper->findByAzubiAndWocheVon($azubi->getId(), $wocheVon);
		} catch (DoesNotExistException) {
			$woche = new Woche();
			$woche->setAzubiId($azubi->getId());
			$woche->setNachweisNr($this->wocheMapper->letzteNachweisNr($azubi->getId()) + 1);
			$woche->setWocheVon($wocheVon);
			$woche->setWocheBis(self::wocheBisFuer($wocheVon));
			$woche->setStatus(Woche::STATUS_OFFEN);
			$woche->setCreatedAt(time());
			return $this->wocheMapper->insert($woche);
		}
	}

	/**
	 * @throws \DomainException wenn die Woche gerade nicht bearbeitbar ist
	 */
	public function pruefeBearbeitbar(Woche $woche): void {
		if (!in_array($woche->getStatus(), [Woche::STATUS_OFFEN, Woche::STATUS_ZURUECKGEWIESEN], true)) {
			throw new \DomainException('Diese Woche ist bereits eingereicht/akzeptiert und kann nicht mehr bearbeitet werden.');
		}
	}

	/**
	 * Speichert/ueberschreibt einen Tageseintrag.
	 * @param array<array{fachId:int,stunden:float,inhalt?:?string,noteArt?:?string,note?:?int}> $faecher nur bei tag_typ=berufsschule ausgewertet
	 * @throws \DomainException wenn die Woche nicht bearbeitbar ist oder eine Note ungueltig ist
	 */
	public function speichereEintrag(
		Azubi $azubi,
		string $datum,
		string $tagTyp,
		?string $taetigkeit,
		?float $stunden,
		array $faecher,
	): Eintrag {
		$woche = $this->getOderErstelleWoche($azubi, self::wocheVonFuer($datum));
		$this->pruefeBearbeitbar($woche);

		try {
			$eintrag = $this->eintragMapper->findByAzubiAndDatum($azubi->getId(), $datum);
		} catch (DoesNotExistException) {
			$eintrag = new Eintrag();
			$eintrag->setAzubiId($azubi->getId());
			$eintrag->setDatum($datum);
			$eintrag->setCreatedAt(time());
		}
		$eintrag->setTagTyp($tagTyp);
		$eintrag->setTaetigkeit($tagTyp === Eintrag::TAG_TYP_BETRIEB ? $taetigkeit : null);
		$eintrag->setStunden($stunden);
		$eintrag->setUpdatedAt(time());
		$eintrag = $eintrag->getId() === null
			? $this->eintragMapper->insert($eintrag)
			: $this->eintragMapper->update($eintrag);

		if ($tagTyp === Eintrag::TAG_TYP_BERUFSSCHULE) {
			// Vor dem Loeschen der bisherigen Zeilen validieren, damit ein
			// ungueltiger Fach-Eintrag nicht zu einem Tag ohne (oder mit nur
			// teilweise neu geschriebenen) Fach-Zeilen fuehrt.
			foreach ($faecher as $fach) {
				$noteArt = ($fach['noteArt'] ?? null) ?: null;
				if ($noteArt !== null && !array_key_exists($noteArt, FachEintrag::NOTE_GEWICHT)) {
					throw new \DomainException('Ungueltige Notenart.');
				}
				$note = $fach['note'] ?? null;
				if ($noteArt !== null && ($note === null || $note < 1 || $note > 6)) {
					throw new \DomainException('Note muss zwischen 1 und 6 liegen.');
				}
			}
		}

		$this->fachEintragMapper->deleteByEintragId($eintrag->getId());
		if ($tagTyp === Eintrag::TAG_TYP_BERUFSSCHULE) {
			$position = 0;
			foreach ($faecher as $fach) {
				$noteArt = ($fach['noteArt'] ?? null) ?: null;
				$zeile = new FachEintrag();
				$zeile->setEintragId($eintrag->getId());
				$zeile->setPosition($position++);
				$zeile->setFachId((int)$fach['fachId']);
				$zeile->setStunden((float)$fach['stunden']);
				$inhalt = $fach['inhalt'] ?? null;
				$zeile->setInhalt($inhalt !== null && $inhalt !== '' ? $inhalt : null);
				$zeile->setNoteArt($noteArt);
				$zeile->setNote($noteArt !== null ? (int)$fach['note'] : null);
				$this->fachEintragMapper->insert($zeile);
			}
		}

		return $eintrag;
	}

	/**
	 * Alle aktiven Tage der Woche (Mo-Fr, plus zugeschaltete Sa/So).
	 * @return string[] Datumswerte Y-m-d
	 */
	public function aktiveTage(string $wocheVon, bool $samstagAktiv, bool $sonntagAktiv): array {
		$tage = [];
		for ($i = 0; $i < 5; $i++) {
			$tage[] = (new DateTimeImmutable($wocheVon))->add(new DateInterval('P' . $i . 'D'))->format('Y-m-d');
		}
		if ($samstagAktiv) {
			$tage[] = (new DateTimeImmutable($wocheVon))->add(new DateInterval('P5D'))->format('Y-m-d');
		}
		if ($sonntagAktiv) {
			$tage[] = (new DateTimeImmutable($wocheVon))->add(new DateInterval('P6D'))->format('Y-m-d');
		}
		return $tage;
	}

	/**
	 * Vollstaendigkeitspruefung vor Einreichung (Plan Abschnitt 3): jeder
	 * aktive Tag braucht einen Eintrag.
	 */
	public function istVollstaendig(Azubi $azubi, string $wocheVon, bool $samstagAktiv, bool $sonntagAktiv): bool {
		$erfasst = array_map(
			static fn (Eintrag $e) => $e->getDatum(),
			$this->eintragMapper->findByAzubiAndDateRange($azubi->getId(), $wocheVon, self::wocheBisFuer($wocheVon)),
		);
		foreach ($this->aktiveTage($wocheVon, $samstagAktiv, $sonntagAktiv) as $tag) {
			if (!in_array($tag, $erfasst, true)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * "Woche einreichen" (Plan Abschnitt 3). Benachrichtigt ausschliesslich
	 * den fuer den Azubi hinterlegten Berichtsheft-Verantwortlichen (In-App
	 * + E-Mail) - nicht die ganze Ausbilder-Gruppe.
	 * @throws \DomainException wenn nicht vollstaendig oder nicht bearbeitbar
	 */
	public function einreichen(Azubi $azubi, Woche $woche, bool $samstagAktiv, bool $sonntagAktiv): Woche {
		$this->pruefeBearbeitbar($woche);
		if (!$this->istVollstaendig($azubi, $woche->getWocheVon(), $samstagAktiv, $sonntagAktiv)) {
			throw new \DomainException('Es fehlen noch Eintraege fuer mindestens einen Tag dieser Woche.');
		}

		$name = trim(($azubi->getVorname() ?? '') . ' ' . ($azubi->getNachname() ?? '')) ?: $azubi->getUserId();
		$woche->setStatus(Woche::STATUS_EINGEREICHT);
		$woche->setEingereichtVonUserId($azubi->getUserId());
		$woche->setEingereichtVonName($name);
		$woche->setEingereichtAm(time());
		$woche = $this->wocheMapper->update($woche);

		$this->benachrichtigeVerantwortlichen($azubi, $woche);

		return $woche;
	}

	private function benachrichtigeVerantwortlichen(Azubi $azubi, Woche $woche): void {
		$empfaenger = $azubi->getVerantwortlicherAusbilderUserId();

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($empfaenger)
			->setObject('woche', (string)$woche->getId())
			->setDateTime(new \DateTime())
			->setSubject('woche-eingereicht', [
				'nachweisNr' => $woche->getNachweisNr(),
				'azubiName' => $woche->getEingereichtVonName(),
			]);
		$this->notificationManager->notify($notification);

		$this->mailService->sendeWocheEingereicht($empfaenger, $azubi, $woche);
	}
}
