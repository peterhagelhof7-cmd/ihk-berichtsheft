<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\Db\BerufMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Loest den auf dem Azubi gespeicherten Beruf-Schluessel (bh_azubi.
 * ausbildungsberuf) in die zwei getrennt gedruckten Deckblatt-Felder
 * "Ausbildungsberuf" und "Fachrichtung/Schwerpunkt" auf.
 *
 * Frueher eine feste Code-Lookup-Tabelle - jetzt aus dem Ausbilder-pflegbaren
 * Katalog (bh_beruf, siehe BerufController/Migration). Unbekannte Schluessel
 * (z.B. ein zwischenzeitlich geloeschter Beruf) fallen graceful auf den
 * rohen Schluessel bzw. "—" zurueck, statt zu scheitern.
 */
class AusbildungsberufHelper {
	public function __construct(
		private BerufMapper $berufMapper,
	) {
	}

	public function getAusbildungsberufBezeichnung(string $enum): string {
		try {
			return $this->berufMapper->findByKey($enum)->getBezeichnung();
		} catch (DoesNotExistException) {
			return $enum;
		}
	}

	public function getFachrichtung(string $enum): string {
		try {
			$fachrichtung = $this->berufMapper->findByKey($enum)->getFachrichtung();
			return ($fachrichtung !== null && $fachrichtung !== '') ? $fachrichtung : '—';
		} catch (DoesNotExistException) {
			return '—';
		}
	}
}
