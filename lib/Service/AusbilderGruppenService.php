<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroup;
use OCP\IGroupManager;

/**
 * Kapselt den Zugriff auf die "Ausbilder-Gruppe" (Plan Abschnitt 2,
 * "Gleichberechtigtes Ausbilder-Modell"): alle Mitglieder dieser
 * Nextcloud-Gruppe haben gleichberechtigten Zugriff auf alle Azubis
 * (Vertretungsfall bei Krankheit/Urlaub). Der tatsaechliche Gruppenname ist
 * app-weit konfigurierbar (Default siehe Application::DEFAULT_AUSBILDER_GRUPPE).
 *
 * Mitglieder der Gruppe selbst werden ganz regulaer ueber Nextclouds eigene
 * Benutzer-/Gruppenverwaltung gepflegt - diese Klasse liest nur, sie legt
 * keine Gruppe/Mitgliedschaft an.
 */
class AusbilderGruppenService {
	private const CONFIG_KEY = 'ausbilder_gruppe';

	public function __construct(
		private IGroupManager $groupManager,
		private IAppConfig $appConfig,
	) {
	}

	public function getGruppenName(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_KEY,
			Application::DEFAULT_AUSBILDER_GRUPPE,
		);
	}

	public function setGruppenName(string $gruppenName): void {
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, $gruppenName);
	}

	public function isAusbilder(string $userId): bool {
		return $this->groupManager->isInGroup($userId, $this->getGruppenName());
	}

	public function getGruppe(): ?IGroup {
		return $this->groupManager->get($this->getGruppenName());
	}

	/** @return string[] Nextcloud-UIDs aller Ausbilder-Gruppenmitglieder */
	public function getAlleAusbilderUserIds(): array {
		$gruppe = $this->getGruppe();
		if ($gruppe === null) {
			return [];
		}
		return array_map(
			static fn ($user) => $user->getUID(),
			$gruppe->getUsers(),
		);
	}
}
