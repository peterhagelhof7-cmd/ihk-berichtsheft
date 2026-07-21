<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\NotenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Notenstand je Azubi fuer die Ausbilder-Notenverwaltung - wie
 * PruefungController bewusst offen fuer JEDES Mitglied der
 * Ausbilder-Gruppe (nicht nur den fuer den Azubi hinterlegten
 * Verantwortlichen), die Azubi-Auswahl selbst liefert bereits der
 * bestehende AzubiController::index() (ALLE Azubis, s. dort).
 */
class NotenController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AzubiMapper $azubiMapper,
		private NotenService $notenService,
		private AusbilderGruppenService $ausbilderGruppenService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function verifyAusbilder(): ?JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null || !$this->ausbilderGruppenService->isAusbilder($user->getUID())) {
			return new JSONResponse(['error' => 'Nur Mitglieder der Ausbilder-Gruppe duerfen dies.'], 403);
		}
		return null;
	}

	/** Notentabelle des aktuellen Lehrjahrs eines Azubis. */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/noten/{azubiId}')]
	public function index(int $azubiId): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$azubi = $this->azubiMapper->find($azubiId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}

		try {
			return new JSONResponse($this->notenService->aktuelleTabelle($azubi));
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Diesem Azubi ist noch kein Lehrjahr zugewiesen.'], 409);
		}
	}
}
