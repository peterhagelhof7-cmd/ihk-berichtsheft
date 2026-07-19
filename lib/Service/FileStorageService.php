<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

/**
 * Ablage der PDFs im Nextcloud-Dateibereich des Azubis + Gruppen-Share an
 * die Ausbilder-Gruppe (Plan Abschnitt 4, "Zugriffsmodell"). Der
 * "Berichtsheft"-Ordner heisst bewusst nicht generisch, sondern
 * "Berichtsheft - <Nachname>, <Vorname>", damit ein Ausbilder mit vielen
 * Azubis in seiner "Mit mir geteilt"-Ansicht nicht mehrere gleich
 * benannte Ordner unterscheiden muss.
 */
class FileStorageService {
	public function __construct(
		private IRootFolder $rootFolder,
		private IShareManager $shareManager,
		private AusbilderGruppenService $ausbilderGruppenService,
		private IUserManager $userManager,
	) {
	}

	private function ordnername(Azubi $azubi): string {
		$name = trim(($azubi->getNachname() ?? '') . ', ' . ($azubi->getVorname() ?? ''));
		if ($name === ',' || $name === '') {
			$name = $this->userManager->get($azubi->getUserId())?->getDisplayName() ?? $azubi->getUserId();
		}
		return 'Berichtsheft - ' . $name;
	}

	/**
	 * Holt/erzeugt den Berichtsheft-Ordner des Azubis und legt einmalig
	 * (idempotent) einen Read-Only-Gruppen-Share an die Ausbilder-Gruppe
	 * an. Wird bei der Azubi-Aktivierung aufgerufen, nicht pro Export.
	 */
	public function ensureBerichtsheftOrdnerUndGruppenShare(Azubi $azubi): Folder {
		$userFolder = $this->rootFolder->getUserFolder($azubi->getUserId());
		$name = $this->ordnername($azubi);

		try {
			/** @var Folder $ordner */
			$ordner = $userFolder->get($name);
		} catch (NotFoundException) {
			$ordner = $userFolder->newFolder($name);
		}

		$gruppe = $this->ausbilderGruppenService->getGruppenName();
		$bereitsGeteilt = false;
		foreach ($this->shareManager->getSharesBy($azubi->getUserId(), IShare::TYPE_GROUP, $ordner) as $share) {
			if ($share->getSharedWith() === $gruppe) {
				$bereitsGeteilt = true;
				break;
			}
		}
		if (!$bereitsGeteilt) {
			$share = $this->shareManager->newShare();
			$share->setNode($ordner);
			$share->setShareType(IShare::TYPE_GROUP);
			$share->setSharedWith($gruppe);
			$share->setSharedBy($azubi->getUserId());
			$share->setShareOwner($azubi->getUserId());
			$share->setPermissions(Constants::PERMISSION_READ);
			$this->shareManager->createShare($share);
		}

		return $ordner;
	}

	/**
	 * Schreibt/ueberschreibt eine PDF-Datei im Berichtsheft-Ordner des
	 * Azubis (Dateinamenskonvention s. PdfExportService/DeckblattService).
	 */
	public function speicherePdf(Azubi $azubi, string $dateiname, string $inhalt): File {
		$ordner = $this->ensureBerichtsheftOrdnerUndGruppenShare($azubi);

		try {
			/** @var File $datei */
			$datei = $ordner->get($dateiname);
			$datei->putContent($inhalt);
		} catch (NotFoundException) {
			/** @var File $datei */
			$datei = $ordner->newFile($dateiname, $inhalt);
		}

		return $datei;
	}
}
