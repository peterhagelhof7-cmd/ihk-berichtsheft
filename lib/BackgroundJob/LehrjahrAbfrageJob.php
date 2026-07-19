<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\BackgroundJob;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\MailService;
use OCA\Berichtsheft\Service\StammdatenService;
use OCA\Berichtsheft\Service\WocheStatusService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IAppConfig;
use OCP\Notification\IManager as INotificationManager;

/**
 * Fragt die Ausbilder-Gruppe einen Monat vor dem konfigurierten
 * "Ausbildungsjahr-Start"-Stichtag aktiv, welches Lehrjahr ab dem
 * Stichtag fuer jeden Azubi ohne Zuweisung gilt (Plan Abschnitt 2/3, Job
 * 5). Wiederholt sich (Drossel: nicht oefter als alle 3 Tage je
 * Azubi+Stichtag), bis irgendein Ausbilder die Zuweisung gesetzt hat.
 */
class LehrjahrAbfrageJob extends TimedJob {
	private const DROSSEL_TAGE = 3;
	private const CONFIG_PREFIX_LETZTE_ERINNERUNG = 'lehrjahr_erinnerung_';

	public function __construct(
		ITimeFactory $time,
		private AzubiMapper $azubiMapper,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private StammdatenService $stammdatenService,
		private AusbilderGruppenService $ausbilderGruppenService,
		private WocheStatusService $wocheStatusService,
		private INotificationManager $notificationManager,
		private MailService $mailService,
		private IAppConfig $appConfig,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		$this->laufeFuer(date('Y-m-d', $this->time->getTime()));
	}

	/** Oeffentlich fuer den Debug-occ-Befehl. */
	public function laufeFuer(string $heute, bool $ignoriereZeitfenster = false): void {
		$naechsterStichtag = $this->stammdatenService->naechsterStichtagAb($heute);
		$einMonatVorher = (new \DateTimeImmutable($naechsterStichtag))->modify('-1 month')->format('Y-m-d');

		if (!$ignoriereZeitfenster && ($heute < $einMonatVorher || $heute >= $naechsterStichtag)) {
			return;
		}

		$ausbilderIds = $this->ausbilderGruppenService->getAlleAusbilderUserIds();
		if (empty($ausbilderIds)) {
			return;
		}

		foreach ($this->azubiMapper->findActiveOn($heute) as $azubi) {
			if ($this->lehrjahrZuweisungMapper->existsForAzubiAndGueltigAb($azubi->getId(), $naechsterStichtag)) {
				continue;
			}

			$configKey = self::CONFIG_PREFIX_LETZTE_ERINNERUNG . $azubi->getId() . '_' . $naechsterStichtag;
			$letzteErinnerung = $this->appConfig->getValueString(Application::APP_ID, $configKey, '');
			if ($letzteErinnerung !== '' && (new \DateTimeImmutable($letzteErinnerung))->diff(new \DateTimeImmutable($heute))->days < self::DROSSEL_TAGE) {
				continue;
			}

			$azubiName = $this->wocheStatusService->azubiAnzeigename($azubi);
			foreach ($ausbilderIds as $ausbilderUserId) {
				$notification = $this->notificationManager->createNotification();
				$notification->setApp(Application::APP_ID)
					->setUser($ausbilderUserId)
					->setObject('azubi', (string)$azubi->getId())
					->setDateTime(new \DateTime())
					->setSubject('lehrjahr-abfrage', [
						'stichtag' => $naechsterStichtag,
						'azubiName' => $azubiName,
					]);
				$this->notificationManager->notify($notification);

				$this->mailService->sendeLehrjahrAbfrage($ausbilderUserId, $azubiName, $naechsterStichtag);
			}

			$this->appConfig->setValueString(Application::APP_ID, $configKey, $heute);
		}
	}
}
