<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCP\Files\File;
use OCP\Files\IRootFolder;

/**
 * Loest das in StammdatenService hinterlegte Logo (Besitzer-Nutzer-ID +
 * Pfad in dessen eigenem Dateibereich) zu einem base64-Data-URI auf, wie es
 * DeckblattService/PdfExportService fuer den Dompdf-Renderer brauchen -
 * Dompdf hat setIsRemoteEnabled(false) (Sicherheit), Bilder muessen also
 * entweder lokale Dateien oder Data-URIs sein, keine URLs.
 *
 * Der Ausbilder waehlt das Logo per Nextcloud-Dateiauswahl (@nextcloud/
 * dialogs FilePicker) aus seinem EIGENEN Dateibereich (Plan-Entscheidung:
 * kein separater Upload-Mechanismus). Da Stammdaten app-weit (nicht pro
 * Nutzer) gespeichert werden, braucht das Aufloesen denselben
 * "Dateizugriff fuer einen anderen als den eingeloggten Nutzer"-Kniff wie
 * FileStorageService::ensureBerichtsheftOrdnerUndGruppenShare() - z.B. beim
 * PDF-Rendering im Hintergrund-Job-Kontext ist der Besitzer nicht der
 * gerade eingeloggte Nutzer.
 */
class LogoService {
	private const ERLAUBTE_MIMETYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

	public function __construct(
		private StammdatenService $stammdatenService,
		private IRootFolder $rootFolder,
	) {
	}

	/** Fuer die PDF-Templates: das aktuell hinterlegte Logo, oder null (kein Logo gesetzt / nicht mehr ladbar - dann einfach ohne Logo rendern statt Fehler zu werfen). */
	public function getLogoDataUri(): ?string {
		$besitzer = $this->stammdatenService->getLogoOwnerUserId();
		$pfad = $this->stammdatenService->getLogoPath();
		if ($besitzer === null || $pfad === null) {
			return null;
		}
		return $this->ladeAlsDataUri($besitzer, $pfad);
	}

	/** Fuer StammdatenController::update(): prueft beim Speichern, ob die gewaehlte Datei ueberhaupt als Logo taugt, statt den Fehler erst beim naechsten PDF-Rendering (dort still ignoriert) bemerken zu lassen. */
	public function pruefeDatei(string $besitzerUserId, string $pfad): bool {
		return $this->ladeAlsDataUri($besitzerUserId, $pfad) !== null;
	}

	private function ladeAlsDataUri(string $besitzerUserId, string $pfad): ?string {
		try {
			\OC\Files\Filesystem::initMountPoints($besitzerUserId);
			$userFolder = $this->rootFolder->getUserFolder($besitzerUserId);
			$datei = $userFolder->get($pfad);
			if (!$datei instanceof File) {
				return null;
			}
			$mimeType = $datei->getMimeType();
			if (!in_array($mimeType, self::ERLAUBTE_MIMETYPES, true)) {
				return null;
			}
			return 'data:' . $mimeType . ';base64,' . base64_encode($datei->getContent());
		} catch (\Throwable) {
			// Datei geloescht/verschoben/nicht mehr freigegeben etc. - lieber
			// ein PDF ohne Logo als ein fehlgeschlagener Export.
			return null;
		}
	}
}
