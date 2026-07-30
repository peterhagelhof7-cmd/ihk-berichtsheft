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

	/** Findet einen bereits vorhandenen "Berichtsheft - ..."-Ordner des Azubis unter einem anderen (veralteten) Namen. */
	private function findeVorherigenOrdner(Folder $userFolder, string $aktuellerName): ?Folder {
		foreach ($userFolder->getDirectoryListing() as $node) {
			if ($node instanceof Folder
				&& $node->getName() !== $aktuellerName
				&& str_starts_with($node->getName(), 'Berichtsheft - ')) {
				return $node;
			}
		}
		return null;
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
		// Wird waehrend der Azubi-Aktivierung im Kontext des ausfuehrenden
		// Ausbilders aufgerufen, nicht des Azubis selbst - ohne das hier
		// laeuft der Azubi-Nutzer u.U. zum allerersten Mal ueberhaupt durch
		// die Dateisystem-Initialisierung (neu angelegte Azubis haben sich
		// vor der Aktivierung durch den Ausbilder erfahrungsgemaess noch nie
		// eingeloggt, das ist hier der Normalfall, kein Sonderfall). Ohne
		// initMountPoints() bleibt getUserFolder() fuer einen Nutzer ohne
		// vorherige eigene Session inkonsistent: oc_filecache-Eintraege
		// werden angelegt, die physischen Dateien im Datenverzeichnis aber
		// nicht - siehe docs/entscheidungen.md. Etabliertes Nextcloud-Muster
		// fuer "Dateizugriff fuer einen anderen als den eingeloggten Nutzer"
		// (siehe lib/private/Files/View.php, apps/files_versions/lib/
		// Storage.php, apps/files_trashbin/lib/Trashbin.php - alle rufen das
		// vor Dateioperationen fuer einen fremden Nutzer identisch auf).
		\OC\Files\Filesystem::initMountPoints($azubi->getUserId());

		$userFolder = $this->rootFolder->getUserFolder($azubi->getUserId());
		$name = $this->ordnername($azubi);

		try {
			/** @var Folder $ordner */
			$ordner = $userFolder->get($name);
		} catch (NotFoundException) {
			$vorheriger = $this->findeVorherigenOrdner($userFolder, $name);
			if ($vorheriger !== null) {
				// Vorname/Nachname wurden nachtraeglich gesetzt oder
				// geaendert, der Ordnername haengt aber daran (s.
				// ordnername()) - den bestehenden Ordner umbenennen statt
				// eine Ordner-Leiche mit veraltetem Inhalt liegenzulassen
				// (die dem Ausbilder als zusaetzlicher, verwirrender
				// Eintrag unter "Mit mir geteilt" angezeigt wuerde). Der
				// bereits bestehende Gruppen-Share haengt am Datei-Node,
				// nicht am Pfad, und bleibt beim Umbenennen erhalten.
				$vorheriger->move($userFolder->getPath() . '/' . $name);
				$ordner = $vorheriger;
			} else {
				$ordner = $userFolder->newFolder($name);
			}
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
			$share = $this->shareManager->createShare($share);

			// Normalerweise akzeptiert Nextclouds eigener
			// ShareCreatedEvent-Listener Gruppen-Shares automatisch fuer
			// alle Mitglieder. Da diese Freigabe waehrend der
			// Azubi-Aktivierung im Kontext des ausfuehrenden Ausbilders
			// (nicht des Azubis/Besitzers) angelegt wird, wirft die
			// Activity-App dabei einen NotFoundException beim Aufloesen
			// des Pfads fuer den aktuell eingeloggten Nutzer - das bricht
			// die Event-Listener-Kette ab, bevor die Auto-Annahme laeuft,
			// und die Freigabe bleibt fuer alle Ausbilder auf PENDING
			// stehen (unsichtbar unter "Mit mir geteilt"). Deshalb hier
			// explizit statt event-basiert annehmen.
			foreach ($this->ausbilderGruppenService->getAlleAusbilderUserIds() as $ausbilderUserId) {
				$this->shareManager->acceptShare($share, $ausbilderUserId);
			}
		}

		return $ordner;
	}

	/**
	 * Prueft, ob bereits eine Datei mit diesem Namen im Berichtsheft-Ordner
	 * des Azubis liegt - fuer berichtsheft:renumber-nachweise (nur bereits
	 * vorhandene Gesamtnachweis-PDFs neu erzeugen, nicht fuer jeden Azubi
	 * ungefragt einen ersten anlegen, s. dort).
	 */
	public function dateiExistiert(Azubi $azubi, string $dateiname): bool {
		$ordner = $this->ensureBerichtsheftOrdnerUndGruppenShare($azubi);
		try {
			$ordner->get($dateiname);
			return true;
		} catch (NotFoundException) {
			return false;
		}
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
