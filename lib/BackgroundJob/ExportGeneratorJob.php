<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\BackgroundJob;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Export;
use OCA\Berichtsheft\Db\ExportMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Db\WocheMapper;
use OCA\Berichtsheft\Service\PdfExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Notification\IManager as INotificationManager;

/**
 * Prueft je offenem bh_export, ob ALLE 4 zugehoerigen Wochen akzeptiert
 * sind (Plan Abschnitt 3, Job 4 - "darf erst laufen, wenn alle zu
 * verarbeitenden Wochen akzeptiert sind"). Wenn ja: PDF rendern, Status
 * exportiert, Azubi+Ausbilder benachrichtigen. Wenn nein: nichts tun -
 * die Erinnerung "bitte pruefen" laeuft bereits ueber
 * AusbilderDigestJob bzw. sofort bei Einreichung.
 */
class ExportGeneratorJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ExportMapper $exportMapper,
		private WocheMapper $wocheMapper,
		private AzubiMapper $azubiMapper,
		private PdfExportService $pdfExportService,
		private INotificationManager $notificationManager,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		foreach ($this->exportMapper->findWartendAufWochen() as $export) {
			$wochen = $this->wocheMapper->findByExportId($export->getId());
			$alleAkzeptiert = count($wochen) > 0 && array_reduce(
				$wochen,
				static fn (bool $carry, Woche $w) => $carry && $w->getStatus() === Woche::STATUS_AKZEPTIERT,
				true,
			);
			if (!$alleAkzeptiert) {
				continue;
			}

			try {
				$azubi = $this->azubiMapper->find($export->getAzubiId());
			} catch (DoesNotExistException) {
				continue;
			}

			$this->pdfExportService->erzeugeExport($azubi, $export, $wochen);

			$export->setStatus(Export::STATUS_EXPORTIERT);
			$export->setGeneratedAt($this->time->getTime());
			$this->exportMapper->update($export);

			$this->benachrichtige($azubi->getUserId(), $export);
			$this->benachrichtige($azubi->getVerantwortlicherAusbilderUserId(), $export);
		}
	}

	private function benachrichtige(string $userId, Export $export): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setObject('export', (string)$export->getId())
			->setDateTime(new \DateTime())
			->setSubject('export-erzeugt', ['exportNr' => $export->getExportNr()]);
		$this->notificationManager->notify($notification);
	}
}
