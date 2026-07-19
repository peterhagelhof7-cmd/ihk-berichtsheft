<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Controller;

use OCA\Berichtsheft\Db\DigestPraeferenz;
use OCA\Berichtsheft\Db\DigestPraeferenzMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Jeder Ausbilder legt seinen eigenen Wochentag/Uhrzeit fuers Montags-
 * Digest fest (Plan Abschnitt 3: "manche Ausbilder haben montags
 * Homeoffice"). Waehlt er aktiv keinen, bleibt die Zeile leer/NULL und
 * AusbilderDigestJob greift auf den Default (Montag/10 Uhr) zurueck -
 * "wählt er aktiv keinen, wird Montag 10 Uhr gesetzt".
 */
class DigestPraeferenzController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private DigestPraeferenzMapper $digestPraeferenzMapper,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	private function currentUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @param int|null $wochentag ISO 1 (Montag) bis 7 (Sonntag), NULL = Default
	 * @param int|null $uhrzeitStunde 0-23, NULL = Default
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/digest-praeferenz')]
	public function update(?int $wochentag, ?int $uhrzeitStunde): JSONResponse {
		if ($wochentag !== null && ($wochentag < 1 || $wochentag > 7)) {
			return new JSONResponse(['error' => 'Wochentag muss zwischen 1 (Montag) und 7 (Sonntag) liegen.'], 422);
		}
		if ($uhrzeitStunde !== null && ($uhrzeitStunde < 0 || $uhrzeitStunde > 23)) {
			return new JSONResponse(['error' => 'Uhrzeit-Stunde muss zwischen 0 und 23 liegen.'], 422);
		}

		$userId = $this->currentUserId();
		try {
			$praeferenz = $this->digestPraeferenzMapper->findByAusbilderUserId($userId);
		} catch (DoesNotExistException) {
			$praeferenz = new DigestPraeferenz();
			$praeferenz->setAusbilderUserId($userId);
		}
		$praeferenz->setWochentag($wochentag);
		$praeferenz->setUhrzeitStunde($uhrzeitStunde);

		$praeferenz = $praeferenz->getId() === null
			? $this->digestPraeferenzMapper->insert($praeferenz)
			: $this->digestPraeferenzMapper->update($praeferenz);

		return new JSONResponse([
			'wochentag' => $praeferenz->getWochentag(),
			'uhrzeitStunde' => $praeferenz->getUhrzeitStunde(),
		]);
	}
}
