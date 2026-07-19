<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Db\WocheMapper;
use OCA\Berichtsheft\Db\WocheRueckweisung;
use OCA\Berichtsheft\Db\WocheRueckweisungMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\EintragService;
use OCA\Berichtsheft\Service\MailService;
use OCA\Berichtsheft\Service\WocheStatusService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;

/**
 * Akzeptieren/Zurueckweisen eingereichter Wochen - offen fuer JEDES
 * Mitglied der Ausbilder-Gruppe, nicht nur den fuer den jeweiligen Azubi
 * hinterlegten Berichtsheft-Verantwortlichen (Plan Abschnitt 2/3,
 * Vertretungsfall bei Krankheit/Urlaub).
 */
class PruefungController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private WocheMapper $wocheMapper,
		private WocheRueckweisungMapper $wocheRueckweisungMapper,
		private AzubiMapper $azubiMapper,
		private EintragMapper $eintragMapper,
		private FachEintragMapper $fachEintragMapper,
		private FachMapper $fachMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private WocheStatusService $wocheStatusService,
		private MailService $mailService,
		private INotificationManager $notificationManager,
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

	/** Alle eingereichten Wochen ueber ALLE Azubis, nicht gefiltert nach "meine Azubis". */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/pruefung')]
	public function index(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		$result = [];
		foreach ($this->wocheMapper->findEingereicht() as $woche) {
			$result[] = $this->serialize($woche);
		}
		return new JSONResponse($result);
	}

	/**
	 * Atomarer Statuscheck gegen gleichzeitiges Akzeptieren/Zurueckweisen
	 * durch zwei Ausbilder (Plan Abschnitt 3): schlaegt fehl, wenn eine
	 * andere Person die Woche zwischenzeitlich bereits bearbeitet hat.
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/pruefung/{wocheId}/akzeptieren')]
	public function akzeptieren(int $wocheId): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		try {
			$woche = $this->wocheMapper->find($wocheId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Woche nicht gefunden.'], 404);
		}

		$ausbilderUserId = $this->userSession->getUser()->getUID();
		if (!$this->wocheMapper->statusWechselNurWennEingereicht($wocheId, Woche::STATUS_AKZEPTIERT)) {
			return new JSONResponse(['error' => 'Diese Woche wurde bereits von jemand anderem bearbeitet.'], 409);
		}

		// Nach dem atomaren Statuswechsel die restlichen Felder setzen.
		$woche = $this->wocheMapper->find($wocheId);
		$woche->setAkzeptiertVonUserId($ausbilderUserId);
		$woche->setAkzeptiertVonName($this->userManager->get($ausbilderUserId)?->getDisplayName() ?? $ausbilderUserId);
		$woche->setAkzeptiertAm(time());
		$woche = $this->wocheMapper->update($woche);

		try {
			$azubi = $this->azubiMapper->find($this->findAzubiIdFuerWoche($woche));
		} catch (DoesNotExistException) {
			$azubi = null;
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($woche->getEingereichtVonUserId() ?? '')
			->setObject('woche', (string)$woche->getId())
			->setDateTime(new \DateTime())
			->setSubject('woche-akzeptiert', ['nachweisNr' => $woche->getNachweisNr()]);
		$this->notificationManager->notify($notification);

		if ($azubi !== null) {
			$this->mailService->sendeWocheAkzeptiert($azubi->getUserId(), $woche);
		}

		return new JSONResponse($this->serialize($woche));
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/pruefung/{wocheId}/zurueckweisen')]
	public function zurueckweisen(int $wocheId, string $kommentar): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		if (trim($kommentar) === '') {
			return new JSONResponse(['error' => 'Ein Kommentar ist bei einer Zurückweisung Pflicht.'], 422);
		}
		try {
			$woche = $this->wocheMapper->find($wocheId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Woche nicht gefunden.'], 404);
		}

		$ausbilderUserId = $this->userSession->getUser()->getUID();
		if (!$this->wocheMapper->statusWechselNurWennEingereicht($wocheId, Woche::STATUS_ZURUECKGEWIESEN)) {
			return new JSONResponse(['error' => 'Diese Woche wurde bereits von jemand anderem bearbeitet.'], 409);
		}

		$rueckweisung = new WocheRueckweisung();
		$rueckweisung->setWocheId($wocheId);
		$rueckweisung->setAusbilderUserId($ausbilderUserId);
		$rueckweisung->setKommentar($kommentar);
		$rueckweisung->setZurueckgewiesenAm(time());
		$this->wocheRueckweisungMapper->insert($rueckweisung);

		$woche = $this->wocheMapper->find($wocheId);

		try {
			$azubi = $this->azubiMapper->find($this->findAzubiIdFuerWoche($woche));
		} catch (DoesNotExistException) {
			$azubi = null;
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($woche->getEingereichtVonUserId() ?? '')
			->setObject('woche', (string)$woche->getId())
			->setDateTime(new \DateTime())
			->setSubject('woche-zurueckgewiesen', [
				'nachweisNr' => $woche->getNachweisNr(),
				'kommentar' => $kommentar,
			]);
		$this->notificationManager->notify($notification);

		if ($azubi !== null) {
			// Ausbilder-Kommentar ist zwingend Teil dieser E-Mail (Plan Abschnitt 3).
			$this->mailService->sendeWocheZurueckgewiesen($azubi->getUserId(), $woche, $kommentar);
		}

		return new JSONResponse($this->serialize($woche));
	}

	private function findAzubiIdFuerWoche(Woche $woche): int {
		// azubi_id liegt nicht direkt auf dem serialisierten Woche-Objekt,
		// aber als Property der Entity.
		return $woche->getAzubiId();
	}

	private function serialize(Woche $woche): array {
		try {
			$azubi = $this->azubiMapper->find($woche->getAzubiId());
		} catch (DoesNotExistException) {
			$azubi = null;
		}

		return [
			'id' => $woche->getId(),
			'azubiId' => $woche->getAzubiId(),
			'nachweisNr' => $woche->getNachweisNr(),
			'wocheVon' => $woche->getWocheVon(),
			'wocheBis' => $woche->getWocheBis(),
			'status' => $woche->getStatus(),
			'eingereichtVonName' => $woche->getEingereichtVonName(),
			'eingereichtAm' => $woche->getEingereichtAm(),
			'rueckweisungen' => array_map(
				static fn ($r) => [
					'kommentar' => $r->getKommentar(),
					'zurueckgewiesenAm' => $r->getZurueckgewiesenAm(),
				],
				$this->wocheRueckweisungMapper->findByWocheId($woche->getId()),
			),
			'eintraege' => $azubi !== null ? $this->serializeEintraege($azubi, $woche) : [],
		];
	}

	/**
	 * Die eigentlichen Tageseintraege der Woche - unverzichtbar fuer eine
	 * echte inhaltliche Pruefung vor dem Akzeptieren/Zurueckweisen (bislang
	 * fehlte das komplett, die Pruefung-Ansicht zeigte nur Metadaten).
	 * @return array<array{datum:string,tagTyp:string,taetigkeit:?string,stunden:?float,faecher:array}>
	 */
	private function serializeEintraege(Azubi $azubi, Woche $woche): array {
		$eintraege = $this->eintragMapper->findByAzubiAndDateRange(
			$azubi->getId(),
			$woche->getWocheVon(),
			EintragService::wocheBisFuer($woche->getWocheVon()),
		);

		return array_map(function (Eintrag $eintrag): array {
			return [
				'datum' => $eintrag->getDatum(),
				'tagTyp' => $eintrag->getTagTyp(),
				'taetigkeit' => $eintrag->getTaetigkeit(),
				'stunden' => $eintrag->getStunden(),
				'faecher' => $eintrag->getTagTyp() === Eintrag::TAG_TYP_BERUFSSCHULE
					? array_map(function ($fe) {
						$fach = null;
						try {
							$fach = $this->fachMapper->find($fe->getFachId());
						} catch (\Throwable) {
							// Fach zwischenzeitlich geloescht - Name entfaellt.
						}
						return [
							'fachName' => $fach?->getName() ?? '?',
							'stunden' => $fe->getStunden(),
							'inhalt' => $fe->getInhalt(),
						];
					}, $this->fachEintragMapper->findByEintragId($eintrag->getId()))
					: [],
			];
		}, $eintraege);
	}
}
