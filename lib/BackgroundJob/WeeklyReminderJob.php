<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\BackgroundJob;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Service\MailService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Notification\IManager as INotificationManager;

/**
 * Erinnert jeden Montag alle aktiven Azubis daran, die Vorwoche
 * einzureichen (Plan Abschnitt 3, Job 1).
 */
class WeeklyReminderJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private AzubiMapper $azubiMapper,
		private INotificationManager $notificationManager,
		private MailService $mailService,
	) {
		parent::__construct($time);
		$this->setInterval(900);
	}

	protected function run($argument): void {
		$this->laufeFuer(date('Y-m-d', $this->time->getTime()), (int)date('N', $this->time->getTime()));
	}

	/** Oeffentlich fuer den Debug-occ-Befehl (umgeht die Wochentag-Sperre). */
	public function laufeFuer(string $heute, int $isoWochentag, bool $ignoriereWochentag = false): void {
		if (!$ignoriereWochentag && $isoWochentag !== 1) {
			return;
		}

		foreach ($this->azubiMapper->findActiveOn($heute) as $azubi) {
			if ($azubi->getLastReminderSentOn() === $heute) {
				continue;
			}

			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($azubi->getUserId())
				->setObject('azubi', (string)$azubi->getId())
				->setDateTime(new \DateTime())
				->setSubject('wochenerinnerung');
			$this->notificationManager->notify($notification);

			$this->mailService->sendeWochenerinnerung($azubi->getUserId());

			$azubi->setLastReminderSentOn($heute);
			$azubi->setUpdatedAt($this->time->getTime());
			$this->azubiMapper->update($azubi);
		}
	}
}
