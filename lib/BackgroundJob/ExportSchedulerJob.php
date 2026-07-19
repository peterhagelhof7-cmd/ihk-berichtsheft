<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\BackgroundJob;

use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Export;
use OCA\Berichtsheft\Db\ExportMapper;
use OCA\Berichtsheft\Db\WocheMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Legt pro Azubi alle 4 Wochen ein neues bh_export-Buendel an, sobald die
 * dazugehoerigen 4 bh_woche-Zeilen existieren (Plan Abschnitt 3, Job 3) -
 * unabhaengig davon, ob diese Wochen schon akzeptiert sind. Das eigentliche
 * PDF-Rendern (erst wenn alle 4 akzeptiert sind) macht ExportGeneratorJob.
 */
class ExportSchedulerJob extends TimedJob {
	private const WOCHEN_PRO_EXPORT = 4;

	public function __construct(
		ITimeFactory $time,
		private AzubiMapper $azubiMapper,
		private ExportMapper $exportMapper,
		private WocheMapper $wocheMapper,
	) {
		parent::__construct($time);
		$this->setInterval(24 * 60 * 60);
	}

	protected function run($argument): void {
		foreach ($this->azubiMapper->findAll() as $azubi) {
			$naechsteExportNr = $this->exportMapper->letzteExportNr($azubi->getId()) + 1;
			$ersterNachweisNr = ($naechsteExportNr - 1) * self::WOCHEN_PRO_EXPORT + 1;

			$wochen = [];
			for ($i = 0; $i < self::WOCHEN_PRO_EXPORT; $i++) {
				try {
					$wochen[] = $this->wocheMapper->findByAzubiAndNachweisNr($azubi->getId(), $ersterNachweisNr + $i);
				} catch (DoesNotExistException) {
					continue 2; // Noch nicht alle 4 Wochen vorhanden - dieser Azubi ist dran, sobald sie existieren.
				}
			}

			$export = new Export();
			$export->setAzubiId($azubi->getId());
			$export->setExportNr($naechsteExportNr);
			$export->setZeitraumVon($wochen[0]->getWocheVon());
			$export->setZeitraumBis($wochen[count($wochen) - 1]->getWocheBis());
			$export->setStatus(Export::STATUS_WARTET_AUF_WOCHEN);
			$export->setCreatedAt($this->time->getTime());
			$export = $this->exportMapper->insert($export);

			foreach ($wochen as $woche) {
				$woche->setExportId($export->getId());
				$this->wocheMapper->update($woche);
			}
		}
	}
}
