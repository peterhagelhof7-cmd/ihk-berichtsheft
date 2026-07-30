<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\Server;

/**
 * Erzeugt EINMALIG das IHK-Pflicht-Deckblatt pro Azubi (Plan Abschnitt 1/4)
 * - kein Teil der wiederkehrenden 4-Wochen-Exporte. Wird direkt nach der
 * Azubi-Aktivierung aufgerufen (AzubiController::aktivieren) sowie erneut
 * manuell ueber "Deckblatt neu erzeugen" (Admin-/Personal-Settings), falls
 * sich Stammdaten spaeter aendern. Ueberschreibt bei erneuter Erzeugung
 * dieselbe Datei "<Nachname> <Vorname>-00.pdf".
 */
class DeckblattService {
	public function __construct(
		private StammdatenService $stammdatenService,
		private AusbildungsberufHelper $ausbildungsberufHelper,
		private FileStorageService $fileStorageService,
		private LogoService $logoService,
		private IUserManager $userManager,
	) {
	}

	private function dateiname(Azubi $azubi): string {
		return PdfExportService::azubiDateinameBasis($azubi, $this->userManager) . '-00.pdf';
	}

	/** Oeffentlich, damit GesamtExportService das Deckblatt dem IHK-Gesamtnachweis voranstellen kann. */
	public function renderHtml(Azubi $azubi): string {
		$verantwortlicherName = $this->userManager->get($azubi->getVerantwortlicherAusbilderUserId())?->getDisplayName()
			?? $azubi->getVerantwortlicherAusbilderUserId();

		$vars = [
			'nachname' => $azubi->getNachname() ?? '',
			'vorname' => $azubi->getVorname() ?? '',
			'betriebsAdresse' => $this->stammdatenService->getBetriebAdresse(),
			'ausbildungsberuf' => $this->ausbildungsberufHelper->getAusbildungsberufBezeichnung($azubi->getAusbildungsberuf()),
			'fachrichtung' => $this->ausbildungsberufHelper->getFachrichtung($azubi->getAusbildungsberuf()),
			'betriebsName' => $this->stammdatenService->getBetriebName(),
			'verantwortlicherName' => $verantwortlicherName,
			'logoDataUri' => $this->logoService->getLogoDataUri(),
		];

		return $this->renderTemplate(
			Server::get(IAppManager::class)->getAppPath(Application::APP_ID) . '/templates/pdf/deckblatt.php',
			$vars,
		);
	}

	public function erzeugen(Azubi $azubi): void {
		$html = $this->renderHtml($azubi);

		$options = new DompdfOptions();
		$options->setIsRemoteEnabled(false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4');
		$dompdf->render();

		$this->fileStorageService->speicherePdf($azubi, $this->dateiname($azubi), $dompdf->output());
	}

	private function renderTemplate(string $pfad, array $vars): string {
		extract($vars, EXTR_SKIP);
		ob_start();
		require $pfad;
		return (string)ob_get_clean();
	}
}
