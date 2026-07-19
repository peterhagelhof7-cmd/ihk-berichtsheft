<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Service\AusbilderGruppenService;
use OCA\Berichtsheft\Service\StammdatenService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * App-weite Betriebs-Stammdaten (Plan Abschnitt 1/2), s. StammdatenService
 * fuer die Config-Keys/Defaults.
 */
class StammdatenController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
		private StammdatenService $stammdatenService,
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
	#[FrontpageRoute(verb: 'GET', url: '/api/stammdaten')]
	public function index(): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		return new JSONResponse([
			'ausbildungsbetriebName' => $this->stammdatenService->getBetriebName(),
			'ausbildungsbetriebAdresse' => $this->stammdatenService->getBetriebAdresse(),
			'ausbildungsjahrStart' => $this->stammdatenService->getAusbildungsjahrStart(),
			'ausbilderGruppe' => $this->ausbilderGruppenService->getGruppenName(),
		]);
	}

	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/stammdaten')]
	public function update(
		?string $ausbildungsbetriebName = null,
		?string $ausbildungsbetriebAdresse = null,
		?string $ausbildungsjahrStart = null,
		?string $ausbilderGruppe = null,
	): JSONResponse {
		if ($fail = $this->verifyAusbilder()) {
			return $fail;
		}
		if ($ausbildungsjahrStart !== null && !preg_match('/^\d{2}-\d{2}$/', $ausbildungsjahrStart)) {
			return new JSONResponse(['error' => 'Ausbildungsjahr-Start muss im Format MM-TT angegeben werden, z.B. 09-01.'], 422);
		}
		if ($ausbildungsbetriebName !== null) {
			$this->appConfig->setValueString(Application::APP_ID, StammdatenService::KEY_BETRIEB_NAME, $ausbildungsbetriebName);
		}
		if ($ausbildungsbetriebAdresse !== null) {
			$this->appConfig->setValueString(Application::APP_ID, StammdatenService::KEY_BETRIEB_ADRESSE, $ausbildungsbetriebAdresse);
		}
		if ($ausbildungsjahrStart !== null) {
			$this->appConfig->setValueString(Application::APP_ID, StammdatenService::KEY_AUSBILDUNGSJAHR_START, $ausbildungsjahrStart);
		}
		if ($ausbilderGruppe !== null) {
			$this->ausbilderGruppenService->setGruppenName($ausbilderGruppe);
		}
		return $this->index();
	}
}
