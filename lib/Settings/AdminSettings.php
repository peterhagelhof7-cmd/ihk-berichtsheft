<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Settings;

use OCA\Berichtsheft\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

/**
 * Admin-Oberflaeche (Azubi-/Faecher-/Lehrjahr-Verwaltung, Betriebs-
 * Stammdaten). IDelegatedSettings statt des einfacheren ISettings, damit ein
 * echter Nextcloud-Instanz-Administrator diese Seite an die
 * Ausbilder-Gruppe delegieren kann (Settings > Administration >
 * Basiseinstellungen > "Berichtsheft" der Gruppe zuweisen) - Plan Abschnitt
 * 2, "Gleichberechtigtes Ausbilder-Modell". Diese Delegation ist ein
 * einmaliger, manueller Schritt eines echten Admins nach der Installation,
 * kein rein programmatisch erzwingbarer Zustand.
 */
class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private IL10N $l,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
		return new TemplateResponse(Application::APP_ID, 'settings/admin', [], TemplateResponse::RENDER_AS_BLANK);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => [
				'/.*/',
			],
		];
	}
}
