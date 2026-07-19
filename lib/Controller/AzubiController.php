<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisung;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\DeckblattService;
use OCA\Berichtsheft\Service\FileStorageService;
use OCA\Berichtsheft\Service\GesamtExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDateTimeFormatter;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Verwaltung der Azubi-Stammdaten durch die Ausbilder-Gruppe. Die
 * Sichtbarkeit der zugehoerigen Admin-Oberflaeche wird bereits durch
 * IDelegatedSettings (AdminSettings) geregelt, hier zusaetzlich eine
 * eigene Berechtigungspruefung je Endpunkt (Verteidigung in der Tiefe -
 * ein direkter API-Aufruf ohne die UI muss ebenso abgelehnt werden).
 */
class AzubiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AzubiMapper $azubiMapper,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private DeckblattService $deckblattService,
		private GesamtExportService $gesamtExportService,
		private FileStorageService $fileStorageService,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function currentUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function verifyAusbilder(): ?JSONResponse {
		if (!$this->ausbilderGruppenService->isAusbilder($this->currentUserId())) {
			return new JSONResponse(['error' => 'Nur Mitglieder der Ausbilder-Gruppe duerfen dies.'], 403);
		}
		return null;
	}

	/**
	 * Liste aller Nextcloud-Benutzer inkl. Markierung, welche bereits als
	 * Azubi aktiviert sind - Grundlage der Azubi-Verwaltungstabelle.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/azubi')]
	public function index(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}

		$azubis = $this->azubiMapper->findAll();
		$azubisByUserId = [];
		foreach ($azubis as $azubi) {
			$azubisByUserId[$azubi->getUserId()] = $azubi;
		}

		$result = [];
		$this->userManager->callForAllUsers(function ($user) use (&$result, $azubisByUserId): void {
			$azubi = $azubisByUserId[$user->getUID()] ?? null;
			// Mitglieder der Ausbilder-Gruppe koennen nicht (mehr) als Azubi
			// aktiviert werden (Doppelrolle bewusst unterbunden, s.
			// aktivieren()) - sie tauchen deshalb in der Auswahl gar nicht
			// erst auf. Bereits bestehende Azubi-Datensaetze (z.B. aus der
			// Zeit vor dieser Einschraenkung) bleiben hier trotzdem sichtbar,
			// damit nichts unbemerkt aus der Verwaltung verschwindet.
			if ($azubi === null && $this->ausbilderGruppenService->isAusbilder($user->getUID())) {
				return;
			}
			$result[] = [
				'userId' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'istAzubi' => $azubi !== null,
				'azubi' => $azubi !== null ? $this->serialize($azubi) : null,
			];
		});
		return new JSONResponse($result);
	}

	/**
	 * Liste aller Mitglieder der Ausbilder-Gruppe - Grundlage fuer die
	 * "Berichtsheft-Verantwortliche/r"-Auswahl im Aktivierungsformular
	 * (Plan Abschnitt 2: nur Ausbilder-Gruppenmitglieder duerfen dort
	 * gewaehlt werden, s. auch die Pruefung in aktivieren()/update()).
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/ausbilder')]
	public function ausbilderListe(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}

		$result = [];
		foreach ($this->ausbilderGruppenService->getAlleAusbilderUserIds() as $userId) {
			$result[] = [
				'userId' => $userId,
				'displayName' => $this->userManager->get($userId)?->getDisplayName() ?? $userId,
			];
		}
		return new JSONResponse($result);
	}

	/**
	 * Aktiviert einen Nextcloud-Benutzer als Azubi. Legt zugleich die
	 * initiale Lehrjahr-Zuweisung an (Plan Abschnitt 2, "Ausnahme -
	 * Erstbefuellung bei der Azubi-Aktivierung") und stoesst die
	 * einmalige Deckblatt-Erzeugung sowie den Gruppen-Share des
	 * Berichtsheft-Ordners an (Plan Abschnitt 4).
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/azubi/{userId}/aktivieren')]
	public function aktivieren(
		string $userId,
		string $ausbildungsberuf,
		string $ausbildungsstart,
		int $ausbildungsjahrStartWert,
		int $lehrjahrStartWert,
		string $verantwortlicherAusbilderUserId,
		?string $ausbildungsabteilung = null,
	): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		if (!$this->userManager->userExists($userId)) {
			return new JSONResponse(['error' => 'Nextcloud-Benutzer nicht gefunden.'], 404);
		}
		if ($this->azubiMapper->existsForUserId($userId)) {
			return new JSONResponse(['error' => 'Dieser Benutzer ist bereits als Azubi aktiviert.'], 409);
		}
		if ($this->ausbilderGruppenService->isAusbilder($userId)) {
			// Doppelrolle bewusst unterbunden (Nutzerentscheidung, s.
			// index()) - Verteidigung in der Tiefe: die Admin-Oberflaeche
			// zeigt Ausbilder-Gruppenmitglieder gar nicht erst zur Auswahl
			// an, ein direkter API-Aufruf muss trotzdem ebenso abgelehnt
			// werden.
			return new JSONResponse(['error' => 'Mitglieder der Ausbilder-Gruppe koennen nicht als Azubi aktiviert werden.'], 422);
		}
		if (!$this->userManager->userExists($verantwortlicherAusbilderUserId)
			|| !$this->ausbilderGruppenService->isAusbilder($verantwortlicherAusbilderUserId)) {
			return new JSONResponse(['error' => 'Der Berichtsheft-Verantwortliche muss Mitglied der Ausbilder-Gruppe sein.'], 422);
		}

		$now = time();
		$azubi = new Azubi();
		$azubi->setUserId($userId);
		$azubi->setAusbildungsberuf($ausbildungsberuf);
		$azubi->setAusbildungsstart($ausbildungsstart);
		$azubi->setAusbildungsjahrStartWert($ausbildungsjahrStartWert);
		$azubi->setVerantwortlicherAusbilderUserId($verantwortlicherAusbilderUserId);
		$azubi->setAusbildungsabteilung($ausbildungsabteilung);
		$azubi->setVorname(null);
		$azubi->setNachname(null);
		$azubi->setLastReminderSentOn(null);
		$azubi->setStatus(Azubi::STATUS_AKTIV);
		$azubi->setCreatedAt($now);
		$azubi->setUpdatedAt($now);
		$azubi = $this->azubiMapper->insert($azubi);

		$zuweisung = new LehrjahrZuweisung();
		$zuweisung->setAzubiId($azubi->getId());
		$zuweisung->setGueltigAb($ausbildungsstart);
		$zuweisung->setLehrjahr($lehrjahrStartWert);
		$zuweisung->setFestgelegtVonUserId($this->currentUserId());
		$zuweisung->setFestgelegtAm($now);
		$this->lehrjahrZuweisungMapper->insert($zuweisung);

		$this->fileStorageService->ensureBerichtsheftOrdnerUndGruppenShare($azubi);
		$this->deckblattService->erzeugen($azubi);

		return new JSONResponse($this->serialize($azubi), 201);
	}

	/**
	 * Nachtraeglich aenderbare Felder (bewusst NICHT: ausbildungsstart,
	 * ausbildungsjahrStartWert, initiales Lehrjahr - s. Plan Phase 3).
	 * ausbildungsberuf ist nachtraeglich aenderbar fuer den Berufswechsel-
	 * Fall - einfaches Ueberschreiben ohne eigene Historie (bewusste
	 * Entscheidung, s. Ausbildung-beenden/Berufswechsel-Nachtrag).
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/azubi/{id}')]
	public function update(
		int $id,
		?string $ausbildungsberuf = null,
		?string $verantwortlicherAusbilderUserId = null,
		?string $ausbildungsabteilung = null,
	): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}

		$deckblattVeraltet = false;
		if ($ausbildungsberuf !== null && $ausbildungsberuf !== $azubi->getAusbildungsberuf()) {
			$azubi->setAusbildungsberuf($ausbildungsberuf);
			$deckblattVeraltet = true;
		}
		if ($verantwortlicherAusbilderUserId !== null && $verantwortlicherAusbilderUserId !== $azubi->getVerantwortlicherAusbilderUserId()) {
			if (!$this->ausbilderGruppenService->isAusbilder($verantwortlicherAusbilderUserId)) {
				return new JSONResponse(['error' => 'Der Berichtsheft-Verantwortliche muss Mitglied der Ausbilder-Gruppe sein.'], 422);
			}
			$azubi->setVerantwortlicherAusbilderUserId($verantwortlicherAusbilderUserId);
			$deckblattVeraltet = true;
		}
		if ($ausbildungsabteilung !== null) {
			$azubi->setAusbildungsabteilung($ausbildungsabteilung);
		}
		$azubi->setUpdatedAt(time());
		$azubi = $this->azubiMapper->update($azubi);

		if ($deckblattVeraltet) {
			// Ausbildungsberuf/Verantwortlicher aendert sich -> Deckblatt-Druckfelder veraltet.
			$this->deckblattService->erzeugen($azubi);
		}

		return new JSONResponse($this->serialize($azubi));
	}

	/**
	 * Ausbildung beenden (Vertragsende, Betriebswechsel weg von hier, usw.)
	 * - keine Loeschung: Azubi-Zeile, alle Wochen/PDFs und der Datei-Ordner
	 * bleiben unveraendert, der Azubi verschwindet nur aus der aktiven
	 * Verwaltungsliste (Frontend-Filter) und ist jederzeit reaktivierbar.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/azubi/{id}/beenden')]
	public function beenden(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		$azubi->setStatus(Azubi::STATUS_BEENDET);
		$azubi->setUpdatedAt(time());
		$azubi = $this->azubiMapper->update($azubi);
		return new JSONResponse($this->serialize($azubi));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/azubi/{id}/reaktivieren')]
	public function reaktivieren(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		$azubi->setStatus(Azubi::STATUS_AKTIV);
		$azubi->setUpdatedAt(time());
		$azubi = $this->azubiMapper->update($azubi);
		return new JSONResponse($this->serialize($azubi));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/azubi/{id}/deckblatt-neu-erzeugen')]
	public function deckblattNeuErzeugen(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		$this->deckblattService->erzeugen($azubi);
		return new JSONResponse(['ok' => true]);
	}

	/**
	 * IHK-Gesamtnachweis: Deckblatt + alle akzeptierten Wochen in einer PDF,
	 * ausschliesslich manuell durch einen Ausbilder ausloesbar. Landet im
	 * ohnehin geteilten Berichtsheft-Ordner - Azubi und Ausbilder sehen die
	 * Datei automatisch beide (Plan-Nachtrag, IHK-Vorgabe max. 35 MB).
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/azubi/{id}/gesamtexport-erzeugen')]
	public function gesamtexportErzeugen(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		try {
			$this->gesamtExportService->erzeugen($azubi);
		} catch (\DomainException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 422);
		}
		return new JSONResponse(['ok' => true]);
	}

	private function serialize(Azubi $azubi): array {
		return [
			'id' => $azubi->getId(),
			'userId' => $azubi->getUserId(),
			'displayName' => $this->userManager->get($azubi->getUserId())?->getDisplayName() ?? $azubi->getUserId(),
			'ausbildungsberuf' => $azubi->getAusbildungsberuf(),
			'ausbildungsstart' => $azubi->getAusbildungsstart(),
			'ausbildungsjahrStartWert' => $azubi->getAusbildungsjahrStartWert(),
			'verantwortlicherAusbilderUserId' => $azubi->getVerantwortlicherAusbilderUserId(),
			'ausbildungsabteilung' => $azubi->getAusbildungsabteilung(),
			'vorname' => $azubi->getVorname(),
			'nachname' => $azubi->getNachname(),
			'status' => $azubi->getStatus(),
		];
	}
}
