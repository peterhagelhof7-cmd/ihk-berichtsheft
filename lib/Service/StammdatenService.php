<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Zentraler Zugriff auf die app-weiten Betriebs-Stammdaten (Plan Abschnitt
 * 1/2): rechtliche Firmierung + Betriebsadresse fuers Deckblatt,
 * "Ausbildungsjahr-Start" (Monat/Tag) fuer die Lehrjahr-Abfrage. Ein
 * Betrieb = eine Nextcloud-Instanz. Von StammdatenController (Lesen/
 * Schreiben), LehrjahrAbfrageJob und DeckblattService gemeinsam genutzt,
 * damit die Config-Keys/Defaults nur an einer Stelle stehen.
 */
class StammdatenService {
	public const KEY_BETRIEB_NAME = 'ausbildungsbetrieb_name';
	public const KEY_BETRIEB_ADRESSE = 'ausbildungsbetrieb_adresse';
	public const KEY_AUSBILDUNGSJAHR_START = 'ausbildungsjahr_start';
	public const DEFAULT_AUSBILDUNGSJAHR_START = '09-01';
	public const KEY_LOGO_OWNER_USER_ID = 'logo_owner_user_id';
	public const KEY_LOGO_PATH = 'logo_path';

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getBetriebName(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_BETRIEB_NAME, '');
	}

	public function getBetriebAdresse(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_BETRIEB_ADRESSE, '');
	}

	/** Nextcloud-Nutzer-ID des Ausbilders, in dessen Dateibereich das Logo liegt (nicht zwingend der aktuell eingeloggte). */
	public function getLogoOwnerUserId(): ?string {
		$wert = $this->appConfig->getValueString(Application::APP_ID, self::KEY_LOGO_OWNER_USER_ID, '');
		return $wert === '' ? null : $wert;
	}

	/** Pfad der Logo-Datei relativ zum Dateibereich von getLogoOwnerUserId(). */
	public function getLogoPath(): ?string {
		$wert = $this->appConfig->getValueString(Application::APP_ID, self::KEY_LOGO_PATH, '');
		return $wert === '' ? null : $wert;
	}

	/** Format "MM-TT", z.B. "09-01". */
	public function getAusbildungsjahrStart(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			self::KEY_AUSBILDUNGSJAHR_START,
			self::DEFAULT_AUSBILDUNGSJAHR_START,
		);
	}

	/**
	 * Naechster Stichtag ab (inkl.) $heute, als vollstaendiges Datum.
	 * Liegt der Stichtag dieses Jahr noch in der Zukunft (oder ist heute),
	 * gilt er; sonst der Stichtag im naechsten Jahr.
	 */
	public function naechsterStichtagAb(string $heute): string {
		[$monat, $tag] = explode('-', $this->getAusbildungsjahrStart());
		$jahr = (int)substr($heute, 0, 4);
		$stichtagDiesesJahr = sprintf('%04d-%s-%s', $jahr, $monat, $tag);
		if ($stichtagDiesesJahr >= $heute) {
			return $stichtagDiesesJahr;
		}
		return sprintf('%04d-%s-%s', $jahr + 1, $monat, $tag);
	}
}
