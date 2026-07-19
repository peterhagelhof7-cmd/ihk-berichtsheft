<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Woche;
use OCP\IUserManager;

/**
 * Baut den gemeinsamen Statusbericht fuer den Montags-Digest (Plan
 * Abschnitt 3: "ein gemeinsamer Statusbericht ueber ALLE Azubis").
 */
class WocheStatusService {
	public function __construct(
		private AzubiMapper $azubiMapper,
		private EintragMapper $eintragMapper,
		private IUserManager $userManager,
	) {
	}

	/**
	 * Kompakter Statustext fuer die Vorwoche je Azubi - fehlende Tage
	 * werden benannt, damit der Digest sofort auf Luecken hinweist.
	 * @return array<array{azubiName:string,wocheVon:string,status:string}>
	 */
	public function statusVorwocheAlleAzubis(string $heute): array {
		$vorwocheVon = EintragService::wocheVonFuer(
			(new \DateTimeImmutable($heute))->modify('-7 days')->format('Y-m-d'),
		);
		$vorwocheBis = EintragService::wocheBisFuer($vorwocheVon);

		$zeilen = [];
		foreach ($this->azubiMapper->findActiveOn($heute) as $azubi) {
			$name = $this->azubiAnzeigename($azubi);
			$eintraege = $this->eintragMapper->findByAzubiAndDateRange($azubi->getId(), $vorwocheVon, $vorwocheBis);
			$fehlendeTage = 5 - count(array_filter(
				$eintraege,
				static fn (Eintrag $e) => $e->getDatum() >= $vorwocheVon,
			));

			$status = $fehlendeTage <= 0
				? 'vollständig erfasst'
				: sprintf('%d Werktag(e) ohne Eintrag', $fehlendeTage);

			$zeilen[] = [
				'azubiName' => $name,
				'wocheVon' => $vorwocheVon,
				'status' => $status,
			];
		}
		return $zeilen;
	}

	public function azubiAnzeigename(Azubi $azubi): string {
		$name = trim(($azubi->getVorname() ?? '') . ' ' . ($azubi->getNachname() ?? ''));
		if ($name !== '') {
			return $name;
		}
		return $this->userManager->get($azubi->getUserId())?->getDisplayName() ?? $azubi->getUserId();
	}
}
