<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

/**
 * Leitet aus dem Ausbildungsberuf-Enum (Plan Abschnitt 2) die zwei getrennt
 * gedruckten Deckblatt-Felder "Ausbildungsberuf" und "Fachrichtung/
 * Schwerpunkt" ab. Feste Code-Lookup-Tabelle statt Enum-Aufspaltung, da die
 * Zuordnung 1:1 ans bestehende Enum gekoppelt ist (keine eigene DB-Tabelle
 * noetig).
 */
class AusbildungsberufHelper {
	/** @var array<string, array{0: string, 1: string}> */
	private const ZUORDNUNG = [
		'fiae' => ['Fachinformatiker/-in', 'Anwendungsentwicklung'],
		'fisi' => ['Fachinformatiker/-in', 'Systemintegration'],
		'fidp' => ['Fachinformatiker/-in', 'Daten- und Prozessanalyse'],
		'fidv' => ['Fachinformatiker/-in', 'Digitale Vernetzung'],
		'kfitsm' => ['Kaufmann/-frau für IT-System-Management', '—'],
		'kfdm' => ['Kaufmann/-frau für Digitalisierungsmanagement', '—'],
		'itse' => ['IT-System-Elektroniker/-in', '—'],
	];

	public function getAusbildungsberufBezeichnung(string $enum): string {
		return self::ZUORDNUNG[$enum][0] ?? $enum;
	}

	public function getFachrichtung(string $enum): string {
		return self::ZUORDNUNG[$enum][1] ?? '—';
	}

	/** @return array<string, string> id => Anzeigename, fuer Auswahllisten */
	public static function alleBerufe(): array {
		$result = [];
		foreach (self::ZUORDNUNG as $id => [$beruf, $fachrichtung]) {
			$result[$id] = $fachrichtung === '—' ? $beruf : "$beruf $fachrichtung";
		}
		return $result;
	}
}
