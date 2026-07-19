<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisung;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Lehrjahr-Zuweisung je Azubi (Plan Abschnitt 2: "vom Ausbilder festgelegt,
 * NICHT berechnet"). Wird sowohl vom manuellen Verwaltungsformular als auch
 * vom Link in der LehrjahrAbfrageJob-Benachrichtigung angesprochen.
 */
class LehrjahrController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private AzubiMapper $azubiMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private IUserManager $userManager,
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

	/** Historie aller Lehrjahr-Zuweisungen eines Azubis, neueste zuerst. */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/lehrjahr/{azubiId}')]
	public function index(int $azubiId): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$this->azubiMapper->find($azubiId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		$alle = $this->lehrjahrZuweisungMapper->findByAzubiId($azubiId);
		return new JSONResponse(array_map([$this, 'serialize'], $alle));
	}

	/** Legt eine neue Lehrjahr-Zuweisung ab einem Stichtag an. */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/lehrjahr/{azubiId}')]
	public function create(int $azubiId, string $gueltigAb, int $lehrjahr): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$this->azubiMapper->find($azubiId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Azubi nicht gefunden.'], 404);
		}
		if ($this->lehrjahrZuweisungMapper->existsForAzubiAndGueltigAb($azubiId, $gueltigAb)) {
			return new JSONResponse(['error' => 'Fuer diesen Stichtag existiert bereits eine Zuweisung.'], 409);
		}

		$zuweisung = new LehrjahrZuweisung();
		$zuweisung->setAzubiId($azubiId);
		$zuweisung->setGueltigAb($gueltigAb);
		$zuweisung->setLehrjahr($lehrjahr);
		$zuweisung->setFestgelegtVonUserId($this->userSession->getUser()->getUID());
		$zuweisung->setFestgelegtAm(time());
		$zuweisung = $this->lehrjahrZuweisungMapper->insert($zuweisung);

		return new JSONResponse($this->serialize($zuweisung), 201);
	}

	private function serialize(LehrjahrZuweisung $z): array {
		return [
			'id' => $z->getId(),
			'azubiId' => $z->getAzubiId(),
			'gueltigAb' => $z->getGueltigAb(),
			'lehrjahr' => $z->getLehrjahr(),
			'festgelegtVonUserId' => $z->getFestgelegtVonUserId(),
			'festgelegtVonName' => $this->userManager->get($z->getFestgelegtVonUserId())?->getDisplayName() ?? $z->getFestgelegtVonUserId(),
			'festgelegtAm' => $z->getFestgelegtAm(),
		];
	}
}
