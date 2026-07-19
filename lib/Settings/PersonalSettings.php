<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Settings;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\DigestPraeferenzMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * Persoenliche Einstellungen - Inhalt haengt von der Rolle des
 * eingeloggten Nutzers ab (Plan Abschnitt 6, Phase 4):
 * Azubi -> Vorname/Nachname-Selbstauskunft fuers Deckblatt.
 * Ausbilder -> zusaetzlich eigene Digest-Zeitpunkt-Praeferenz
 * (Plan Abschnitt 3, "individueller Wochentag/Uhrzeit je Ausbilder").
 * Beides kann gleichzeitig zutreffen (ein Ausbilder kann selbst auch Azubi
 * sein muss die App das nicht ausschliessen).
 */
class PersonalSettings implements ISettings {
	public function __construct(
		private IUserSession $userSession,
		private AzubiMapper $azubiMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private DigestPraeferenzMapper $digestPraeferenzMapper,
		private IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$userId = $this->userSession->getUser()?->getUID();

		$azubiDaten = null;
		if ($userId !== null) {
			try {
				$azubi = $this->azubiMapper->findByUserId($userId);
				$azubiDaten = [
					'vorname' => $azubi->getVorname(),
					'nachname' => $azubi->getNachname(),
				];
			} catch (DoesNotExistException) {
				// Kein Azubi - Vorname/Nachname-Formular wird nicht angezeigt.
			}
		}
		$this->initialState->provideInitialState('azubiDaten', $azubiDaten);

		$istAusbilder = $userId !== null && $this->ausbilderGruppenService->isAusbilder($userId);
		$this->initialState->provideInitialState('istAusbilder', $istAusbilder);

		$digestPraeferenz = null;
		if ($istAusbilder && $userId !== null) {
			try {
				$praeferenz = $this->digestPraeferenzMapper->findByAusbilderUserId($userId);
				$digestPraeferenz = [
					'wochentag' => $praeferenz->getWochentag(),
					'uhrzeitStunde' => $praeferenz->getUhrzeitStunde(),
				];
			} catch (DoesNotExistException) {
				$digestPraeferenz = ['wochentag' => null, 'uhrzeitStunde' => null];
			}
		}
		$this->initialState->provideInitialState('digestPraeferenz', $digestPraeferenz);

		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
		return new TemplateResponse(Application::APP_ID, 'settings/personal', [], TemplateResponse::RENDER_AS_BLANK);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
