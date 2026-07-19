<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;

class PageController extends Controller {
	public function __construct(
		string $appName,
		\OCP\IRequest $request,
		private AzubiMapper $azubiMapper,
		private AusbilderGruppenService $ausbilderGruppenService,
		private IUserSession $userSession,
		private IInitialState $initialState,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$userId = $this->userSession->getUser()?->getUID();

		$this->initialState->provideInitialState('istAusbilder', $userId !== null && $this->ausbilderGruppenService->isAusbilder($userId));
		$this->initialState->provideInitialState('istAzubi', $userId !== null && $this->azubiMapper->existsForUserId($userId));

		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}
}
