<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\AppInfo;

use OCA\Berichtsheft\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'berichtsheft';

	/** Default-Name der Nextcloud-Gruppe, deren Mitglieder als "Ausbilder"
	 * gelten (gleichberechtigter Zugriff auf alle Azubis, s.
	 * AusbilderGruppenService). Tatsächlicher Gruppenname ist über
	 * IAppConfig ueberschreibbar (Admin-Oberflaeche, Phase 3).
	 */
	public const DEFAULT_AUSBILDER_GRUPPE = 'berichtsheft-ausbilder';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
	}
}
