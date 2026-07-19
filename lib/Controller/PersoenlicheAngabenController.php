<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Service\DeckblattService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Vorname/Nachname-Selbstauskunft des Azubis fuers IHK-Deckblatt (Plan
 * Abschnitt 1/2: vom Azubi selbst gepflegt, nicht vom Ausbilder erraten).
 * Loest bei Aenderung eine Deckblatt-Neuerzeugung aus, da der Name dort
 * gedruckt wird.
 */
class PersoenlicheAngabenController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AzubiMapper $azubiMapper,
		private DeckblattService $deckblattService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/persoenliche-angaben')]
	public function update(string $vorname, string $nachname): JSONResponse {
		$userId = $this->userSession->getUser()->getUID();
		try {
			$azubi = $this->azubiMapper->findByUserId($userId);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'Nur aktivierte Azubis koennen persoenliche Angaben pflegen.'], 403);
		}

		$azubi->setVorname($vorname);
		$azubi->setNachname($nachname);
		$azubi->setUpdatedAt(time());
		$azubi = $this->azubiMapper->update($azubi);

		$this->deckblattService->erzeugen($azubi);

		return new JSONResponse([
			'vorname' => $azubi->getVorname(),
			'nachname' => $azubi->getNachname(),
		]);
	}
}
