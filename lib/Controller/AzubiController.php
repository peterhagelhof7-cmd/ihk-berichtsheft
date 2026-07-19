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
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/azubi/{id}')]
	public function update(
		int $id,
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

		$stammdatenGeaendert = false;
		if ($verantwortlicherAusbilderUserId !== null && $verantwortlicherAusbilderUserId !== $azubi->getVerantwortlicherAusbilderUserId()) {
			if (!$this->ausbilderGruppenService->isAusbilder($verantwortlicherAusbilderUserId)) {
				return new JSONResponse(['error' => 'Der Berichtsheft-Verantwortliche muss Mitglied der Ausbilder-Gruppe sein.'], 422);
			}
			$azubi->setVerantwortlicherAusbilderUserId($verantwortlicherAusbilderUserId);
			$stammdatenGeaendert = true;
		}
		if ($ausbildungsabteilung !== null) {
			$azubi->setAusbildungsabteilung($ausbildungsabteilung);
		}
		$azubi->setUpdatedAt(time());
		$azubi = $this->azubiMapper->update($azubi);

		if ($stammdatenGeaendert) {
			// Verantwortlicher Ausbilder aendert sich -> Deckblatt-Druckfeld veraltet.
			$this->deckblattService->erzeugen($azubi);
		}

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
		];
	}
}
