<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Tests\Unit\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\AzubiMapper;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Service\WocheStatusService;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class WocheStatusServiceTest extends TestCase {
	private AzubiMapper&MockObject $azubiMapper;
	private EintragMapper&MockObject $eintragMapper;
	private IUserManager&MockObject $userManager;
	private WocheStatusService $service;

	protected function setUp(): void {
		$this->azubiMapper = $this->createMock(AzubiMapper::class);
		$this->eintragMapper = $this->createMock(EintragMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new WocheStatusService($this->azubiMapper, $this->eintragMapper, $this->userManager);
	}

	private function azubi(?string $vorname, ?string $nachname): Azubi {
		$azubi = new Azubi();
		$azubi->setId(1);
		$azubi->setUserId('azubi1');
		$azubi->setVorname($vorname);
		$azubi->setNachname($nachname);
		return $azubi;
	}

	public function testAzubiAnzeigenameNutztVorUndNachname(): void {
		self::assertSame('Ein Simbel', $this->service->azubiAnzeigename($this->azubi('Ein', 'Simbel')));
	}

	public function testAzubiAnzeigenameFaelltAufNextcloudAnzeigenameZurueck(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('azubi1 (Nextcloud)');
		$this->userManager->method('get')->with('azubi1')->willReturn($user);

		self::assertSame('azubi1 (Nextcloud)', $this->service->azubiAnzeigename($this->azubi(null, null)));
	}

	public function testStatusVorwocheMeldetVollstaendigWennAlleFuenfTageErfasstSind(): void {
		$this->azubiMapper->method('findActiveOn')->willReturn([$this->azubi('Ein', 'Simbel')]);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn(
			array_map([$this, 'eintrag'], ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10']),
		);

		$zeilen = $this->service->statusVorwocheAlleAzubis('2026-07-13');

		self::assertCount(1, $zeilen);
		self::assertSame('vollständig erfasst', $zeilen[0]['status']);
		self::assertSame('2026-07-06', $zeilen[0]['wocheVon']);
	}

	public function testStatusVorwocheNenntFehlendeTage(): void {
		$this->azubiMapper->method('findActiveOn')->willReturn([$this->azubi('Ein', 'Simbel')]);
		// Nur 3 von 5 Werktagen erfasst.
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn(
			array_map([$this, 'eintrag'], ['2026-07-06', '2026-07-07', '2026-07-08']),
		);

		$zeilen = $this->service->statusVorwocheAlleAzubis('2026-07-13');

		self::assertSame('2 Werktag(e) ohne Eintrag', $zeilen[0]['status']);
	}

	private function eintrag(string $datum): Eintrag {
		$eintrag = new Eintrag();
		$eintrag->setAzubiId(1);
		$eintrag->setDatum($datum);
		$eintrag->setTagTyp(Eintrag::TAG_TYP_BETRIEB);
		return $eintrag;
	}
}
