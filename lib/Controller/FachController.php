<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachLehrjahr;
use OCA\Berichtsheft\Db\FachLehrjahrMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Faecher-Katalog + Lehrjahr-Zuordnung (Plan Abschnitt 1/2: "manche Faecher
 * gibt es nur im 1. und 2. Lehrjahr, manche nur im 3.").
 */
class FachController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private FachMapper $fachMapper,
		private FachLehrjahrMapper $fachLehrjahrMapper,
		private FachEintragMapper $fachEintragMapper,
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

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/fach')]
	public function index(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		$result = [];
		foreach ($this->fachMapper->findAll() as $fach) {
			$result[] = $this->serialize($fach);
		}
		return new JSONResponse($result);
	}

	/**
	 * @param int[] $lehrjahre
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/fach')]
	public function create(string $name, array $lehrjahre): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		$now = time();
		$fach = new Fach();
		$fach->setName($name);
		$fach->setCreatedAt($now);
		$fach->setUpdatedAt($now);
		$fach = $this->fachMapper->insert($fach);

		$this->setzeLehrjahre($fach->getId(), $lehrjahre);

		return new JSONResponse($this->serialize($fach), 201);
	}

	/**
	 * @param int[]|null $lehrjahre
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/fach/{id}')]
	public function update(int $id, ?string $name = null, ?array $lehrjahre = null): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$fach = $this->fachMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Fach nicht gefunden.'], 404);
		}
		if ($name !== null) {
			$fach->setName($name);
			$fach->setUpdatedAt(time());
			$fach = $this->fachMapper->update($fach);
		}
		if ($lehrjahre !== null) {
			$this->setzeLehrjahre($id, $lehrjahre);
		}
		return new JSONResponse($this->serialize($fach));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/fach/{id}')]
	public function destroy(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$fach = $this->fachMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Fach nicht gefunden.'], 404);
		}
		// Verwaiste Tagebuch-Bezuege (bh_fach_eintrag.fach_id) mitloeschen,
		// sonst blieben Zeilen mit dangling fach_id zurueck (Anzeige-/PDF-
		// Macke). Auf einer frischen Instanz ohne Eintraege ein No-op.
		$this->fachEintragMapper->deleteByFachId($id);
		$this->fachLehrjahrMapper->deleteByFachId($id);
		$this->fachMapper->delete($fach);
		return new JSONResponse(['ok' => true]);
	}

	/** @param int[] $lehrjahre */
	private function setzeLehrjahre(int $fachId, array $lehrjahre): void {
		$this->fachLehrjahrMapper->deleteByFachId($fachId);
		foreach (array_unique($lehrjahre) as $lehrjahr) {
			$zeile = new FachLehrjahr();
			$zeile->setFachId($fachId);
			$zeile->setLehrjahr((int)$lehrjahr);
			$this->fachLehrjahrMapper->insert($zeile);
		}
	}

	private function serialize(Fach $fach): array {
		$lehrjahre = array_map(
			static fn (FachLehrjahr $fl) => $fl->getLehrjahr(),
			$this->fachLehrjahrMapper->findByFachId($fach->getId()),
		);
		sort($lehrjahre);
		return [
			'id' => $fach->getId(),
			'name' => $fach->getName(),
			'lehrjahre' => array_values($lehrjahre),
		];
	}
}
