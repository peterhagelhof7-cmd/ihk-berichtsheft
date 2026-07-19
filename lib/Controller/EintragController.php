<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Service\EintragService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Tageseintraege + "Woche einreichen" fuer den eingeloggten Azubi selbst
 * (kein Fremdzugriff auf andere Azubis - anders als bei den
 * Ausbilder-Endpunkten gibt es hier keine Gruppen-Berechtigung, jeder Azubi
 * sieht/bearbeitet ausschliesslich seine eigene Woche).
 */
class EintragController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private EintragService $eintragService,
		private EintragMapper $eintragMapper,
		private FachEintragMapper $fachEintragMapper,
		private AzubiMapper $azubiMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/** @throws DoesNotExistException */
	private function currentAzubi(): \OCA\Berichtsheft\Db\Azubi {
		return $this->azubiMapper->findByUserId($this->userSession->getUser()->getUID());
	}

	/**
	 * Liefert Woche + alle Tageseintraege + verfuegbare Faecher fuers
	 * aktuelle Lehrjahr, Grundlage fuer die Wochenansicht.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/woche/{wocheVon}')]
	public function woche(string $wocheVon): JSONResponse {
		try {
			$azubi = $this->currentAzubi();
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Nur aktivierte Azubis haben ein Berichtsheft.'], 403);
		}

		$woche = $this->eintragService->getOderErstelleWoche($azubi, $wocheVon);
		$eintraege = $this->eintragMapper->findByAzubiAndDateRange($azubi->getId(), $wocheVon, EintragService::wocheBisFuer($wocheVon));

		$eintraegeJson = [];
		foreach ($eintraege as $eintrag) {
			$eintraegeJson[$eintrag->getDatum()] = $this->serializeEintrag($eintrag);
		}

		return new JSONResponse([
			'woche' => $this->serializeWoche($woche),
			'eintraege' => $eintraegeJson,
			'verfuegbareFaecher' => array_map(
				static fn (Fach $f) => ['id' => $f->getId(), 'name' => $f->getName()],
				$this->eintragService->getVerfuegbareFaecher($azubi, $wocheVon),
			),
			'ausbildungsjahr' => $this->eintragService->getAusbildungsjahr($azubi, $wocheVon),
		]);
	}

	/**
	 * @param array<array{fachId:int,stunden:float}>|null $faecher
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/eintrag/{datum}')]
	public function speichern(
		string $datum,
		string $tagTyp,
		?string $taetigkeit = null,
		?float $stunden = null,
		?array $faecher = null,
	): JSONResponse {
		try {
			$azubi = $this->currentAzubi();
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Nur aktivierte Azubis haben ein Berichtsheft.'], 403);
		}

		try {
			$eintrag = $this->eintragService->speichereEintrag(
				$azubi,
				$datum,
				$tagTyp,
				$taetigkeit,
				$stunden,
				$faecher ?? [],
			);
		} catch (\DomainException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 409);
		}

		return new JSONResponse($this->serializeEintrag($eintrag));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/woche/{wocheVon}/einreichen')]
	public function einreichen(string $wocheVon, bool $samstagAktiv = false, bool $sonntagAktiv = false): JSONResponse {
		try {
			$azubi = $this->currentAzubi();
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Nur aktivierte Azubis haben ein Berichtsheft.'], 403);
		}

		$woche = $this->eintragService->getOderErstelleWoche($azubi, $wocheVon);
		try {
			$woche = $this->eintragService->einreichen($azubi, $woche, $samstagAktiv, $sonntagAktiv);
		} catch (\DomainException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 409);
		}

		return new JSONResponse($this->serializeWoche($woche));
	}

	private function serializeEintrag(Eintrag $eintrag): array {
		return [
			'datum' => $eintrag->getDatum(),
			'tagTyp' => $eintrag->getTagTyp(),
			'taetigkeit' => $eintrag->getTaetigkeit(),
			'stunden' => $eintrag->getStunden(),
			'faecher' => $eintrag->getTagTyp() === Eintrag::TAG_TYP_BERUFSSCHULE
				? array_map(
					static fn ($fe) => ['fachId' => $fe->getFachId(), 'stunden' => $fe->getStunden()],
					$this->fachEintragMapper->findByEintragId($eintrag->getId()),
				)
				: [],
		];
	}

	private function serializeWoche(Woche $woche): array {
		return [
			'id' => $woche->getId(),
			'nachweisNr' => $woche->getNachweisNr(),
			'wocheVon' => $woche->getWocheVon(),
			'wocheBis' => $woche->getWocheBis(),
			'status' => $woche->getStatus(),
			'eingereichtAm' => $woche->getEingereichtAm(),
			'akzeptiertAm' => $woche->getAkzeptiertAm(),
		];
	}
}
