<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\WocheMapper;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\Server;

/**
 * IHK-Gesamtnachweis: EIN PDF pro Azubi mit vorangestelltem Deckblatt und
 * ALLEN bisher akzeptierten Wochen - kein wiederkehrender Background-Job,
 * sondern ein manueller, ausschliesslich von einem Ausbilder ausloesbarer
 * Vorgang (AzubiController::gesamtexportErzeugen), da die IHK diese
 * gebuendelte Abgabe nur bei Bedarf verlangt (z.B. Ausbildungsende,
 * Zwischenpruefung). Ergebnis landet im ohnehin schon zwischen Azubi
 * (Besitzer) und Ausbilder-Gruppe (Read-Only-Share) geteilten
 * "Berichtsheft - <Name>"-Ordner (FileStorageService) - keine zusaetzliche
 * Freigabe-Logik noetig, beide Seiten sehen dieselbe Datei automatisch.
 * IHK-Vorgabe: Datei darf 35 MB nicht ueberschreiten.
 */
class GesamtExportService {
	private const MAX_BYTES = 35 * 1024 * 1024;

	public function __construct(
		private DeckblattService $deckblattService,
		private PdfExportService $pdfExportService,
		private WocheMapper $wocheMapper,
		private FileStorageService $fileStorageService,
		private IUserManager $userManager,
	) {
	}

	private function dateiname(Azubi $azubi): string {
		return PdfExportService::azubiDateinameBasis($azubi, $this->userManager) . ' - Gesamtnachweis.pdf';
	}

	/** @throws \DomainException wenn keine akzeptierten Wochen vorliegen oder die IHK-Groessenvorgabe (35 MB) ueberschritten wuerde */
	public function erzeugen(Azubi $azubi): void {
		$wochen = $this->wocheMapper->findAkzeptiertByAzubi($azubi->getId());
		if (count($wochen) === 0) {
			throw new \DomainException('Es liegen noch keine akzeptierten Wochen vor - Gesamtnachweis kann noch nicht erzeugt werden.');
		}

		$appPath = Server::get(IAppManager::class)->getAppPath(Application::APP_ID);
		$azubiName = PdfExportService::azubiDateinameBasis($azubi, $this->userManager);

		$html = $this->deckblattService->renderHtml($azubi);
		foreach ($wochen as $woche) {
			// Jede Woche startet auf einer neuen Seite - auch die erste,
			// da ihr bereits das Deckblatt vorausgeht (anders als beim
			// 4-Wochen-Export, wo die erste Woche direkt auf Seite 1 steht).
			$html .= $this->pdfExportService->renderWoche($appPath, $azubi, $woche, $azubiName, true);
		}

		$options = new DompdfOptions();
		$options->setIsRemoteEnabled(false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4');
		$dompdf->render();
		$inhalt = $dompdf->output();

		if (strlen($inhalt) > self::MAX_BYTES) {
			throw new \DomainException(sprintf(
				'Der erzeugte Gesamtnachweis ist mit %.1f MB groesser als die von der IHK vorgegebenen 35 MB und wurde nicht gespeichert.',
				strlen($inhalt) / 1024 / 1024,
			));
		}

		$this->fileStorageService->speicherePdf($azubi, $this->dateiname($azubi), $inhalt);
	}
}
