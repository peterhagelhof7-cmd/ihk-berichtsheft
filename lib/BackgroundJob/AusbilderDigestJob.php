<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\BackgroundJob;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\DigestPraeferenz;
use OCA\Berichtsheft\Db\DigestPraeferenzMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\MailService;
use OCA\Berichtsheft\Service\WocheStatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Notification\IManager as INotificationManager;

/**
 * Individueller Wochentag/Uhrzeit je Ausbilder (Plan Abschnitt 3, Job 2 -
 * "manche Ausbilder haben montags Homeoffice"). Default Montag/10 Uhr,
 * falls ein Ausbilder nie aktiv eine Praeferenz gesetzt hat.
 */
class AusbilderDigestJob extends TimedJob {
	private const DEFAULT_WOCHENTAG = 1;
	private const DEFAULT_STUNDE = 10;

	public function __construct(
		ITimeFactory $time,
		private AusbilderGruppenService $ausbilderGruppenService,
		private DigestPraeferenzMapper $digestPraeferenzMapper,
		private WocheStatusService $wocheStatusService,
		private INotificationManager $notificationManager,
		private MailService $mailService,
	) {
		parent::__construct($time);
		$this->setInterval(900);
	}

	protected function run($argument): void {
		$jetzt = $this->time->getTime();
		$this->laufeFuer(date('Y-m-d', $jetzt), (int)date('N', $jetzt), (int)date('H', $jetzt));
	}

	/** Oeffentlich fuer den Debug-occ-Befehl (umgeht die Wochentag/Uhrzeit-Sperre je Ausbilder). */
	public function laufeFuer(string $heute, int $isoWochentag, int $stunde, bool $ignoriereZeitpunkt = false): void {
		foreach ($this->ausbilderGruppenService->getAlleAusbilderUserIds() as $ausbilderUserId) {
			try {
				$praeferenz = $this->digestPraeferenzMapper->findByAusbilderUserId($ausbilderUserId);
			} catch (DoesNotExistException) {
				$praeferenz = new DigestPraeferenz();
				$praeferenz->setAusbilderUserId($ausbilderUserId);
				$praeferenz->setWochentag(null);
				$praeferenz->setUhrzeitStunde(null);
			}

			$sollWochentag = $praeferenz->getWochentag() ?? self::DEFAULT_WOCHENTAG;
			$sollStunde = $praeferenz->getUhrzeitStunde() ?? self::DEFAULT_STUNDE;

			if (!$ignoriereZeitpunkt && ($isoWochentag !== $sollWochentag || $stunde !== $sollStunde)) {
				continue;
			}
			if ($praeferenz->getLastDigestSentOn() === $heute) {
				continue;
			}

			$zeilen = $this->wocheStatusService->statusVorwocheAlleAzubis($heute);

			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($ausbilderUserId)
				->setObject('digest', $heute)
				->setDateTime(new \DateTime())
				->setSubject('ausbilder-digest');
			$this->notificationManager->notify($notification);

			$this->mailService->sendeAusbilderDigest($ausbilderUserId, $zeilen);

			$praeferenz->setLastDigestSentOn($heute);
			$praeferenz = $praeferenz->getId() === null
				? $this->digestPraeferenzMapper->insert($praeferenz)
				: $this->digestPraeferenzMapper->update($praeferenz);
		}
	}
}
