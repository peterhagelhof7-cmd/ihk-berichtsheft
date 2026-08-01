<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\Beruf;
use OCA\Berichtsheft\Db\BerufMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Ausbildungsberuf-Katalog, Ausbilder-pflegbar (loest die frueher fest im
 * Code hinterlegte Liste ab - so kann das Tool auch fuer andere Branchen als
 * die IT-Berufe genutzt werden). Beruf ist nur ein Deckblatt-Label; die
 * Fächer sind davon unabhaengig (globaler Katalog, siehe FachController).
 */
class BerufController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private BerufMapper $berufMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private IUserSession $userSession,
		private IDBConnection $db,
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
	#[FrontpageRoute(verb: 'GET', url: '/api/beruf')]
	public function index(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		$result = [];
		foreach ($this->berufMapper->findAll() as $beruf) {
			$result[] = $this->serialize($beruf);
		}
		return new JSONResponse($result);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/beruf')]
	public function create(string $bezeichnung, ?string $fachrichtung = null): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		$bezeichnung = trim($bezeichnung);
		if ($bezeichnung === '') {
			return new JSONResponse(['error' => 'Bezeichnung darf nicht leer sein.'], 400);
		}
		$now = time();
		$beruf = new Beruf();
		$beruf->setBerufKey($this->generiereKey($bezeichnung));
		$beruf->setBezeichnung($bezeichnung);
		$beruf->setFachrichtung($this->normFachrichtung($fachrichtung));
		$beruf->setCreatedAt($now);
		$beruf->setUpdatedAt($now);
		$beruf = $this->berufMapper->insert($beruf);
		return new JSONResponse($this->serialize($beruf), 201);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/beruf/{id}')]
	public function update(int $id, ?string $bezeichnung = null, ?string $fachrichtung = null): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$beruf = $this->berufMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Beruf nicht gefunden.'], 404);
		}
		if ($bezeichnung !== null) {
			$bezeichnung = trim($bezeichnung);
			if ($bezeichnung === '') {
				return new JSONResponse(['error' => 'Bezeichnung darf nicht leer sein.'], 400);
			}
			// berufKey bleibt bewusst stabil (Azubis referenzieren ihn) - nur
			// die Anzeige-Bezeichnung/Fachrichtung sind editierbar.
			$beruf->setBezeichnung($bezeichnung);
		}
		if ($fachrichtung !== null) {
			$beruf->setFachrichtung($this->normFachrichtung($fachrichtung));
		}
		$beruf->setUpdatedAt(time());
		$beruf = $this->berufMapper->update($beruf);
		return new JSONResponse($this->serialize($beruf));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/beruf/{id}')]
	public function destroy(int $id): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$beruf = $this->berufMapper->find($id);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Beruf nicht gefunden.'], 404);
		}
		// Nicht loeschen, solange noch Azubis diesen Beruf tragen - sonst
		// zeigte deren Deckblatt nur noch das rohe Kuerzel. Bewusst strenger
		// als bei Fächern (die keine solche Bindung haben).
		$anzahl = $this->anzahlAzubisMitBeruf($beruf->getBerufKey());
		if ($anzahl > 0) {
			return new JSONResponse([
				'error' => "Beruf wird noch von $anzahl Azubi(s) verwendet und kann nicht geloescht werden. "
					. 'Diese Azubis zuerst auf einen anderen Beruf umstellen.',
			], 409);
		}
		$this->berufMapper->delete($beruf);
		return new JSONResponse(['ok' => true]);
	}

	private function anzahlAzubisMitBeruf(string $berufKey): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'cnt'))
			->from('bh_azubi')
			->where($qb->expr()->eq('ausbildungsberuf', $qb->createNamedParameter($berufKey)));
		return (int)$qb->executeQuery()->fetchOne();
	}

	/** Leer/"—"/nur-Leerzeichen -> null (kein Fachrichtungs-Zusatz). */
	private function normFachrichtung(?string $fachrichtung): ?string {
		$f = trim((string)$fachrichtung);
		if ($f === '' || $f === '—' || $f === '-') {
			return null;
		}
		return $f;
	}

	/**
	 * Erzeugt einen kurzen, stabilen, eindeutigen Schluessel aus der
	 * Bezeichnung (max. 16 Zeichen = Spaltenlaenge bh_azubi.ausbildungsberuf).
	 */
	private function generiereKey(string $bezeichnung): string {
		$base = strtolower($bezeichnung);
		$base = strtr($base, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
		$base = preg_replace('/[^a-z0-9]/', '', $base) ?? '';
		if ($base === '') {
			$base = 'beruf';
		}
		$base = substr($base, 0, 12);
		$key = $base;
		$i = 1;
		while ($this->berufMapper->existsByKey($key)) {
			$suffix = (string)(++$i);
			$key = substr($base, 0, 16 - strlen($suffix)) . $suffix;
		}
		return $key;
	}

	private function serialize(Beruf $beruf): array {
		$fachrichtung = $beruf->getFachrichtung();
		$label = $fachrichtung !== null && $fachrichtung !== ''
			? $beruf->getBezeichnung() . ' ' . $fachrichtung
			: $beruf->getBezeichnung();
		return [
			'id' => $beruf->getId(),
			'key' => $beruf->getBerufKey(),
			'bezeichnung' => $beruf->getBezeichnung(),
			'fachrichtung' => $fachrichtung ?? '',
			'label' => $label,
		];
	}
}
