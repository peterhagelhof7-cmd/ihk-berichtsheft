<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Tests\Unit\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintrag;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Service\EintragService;
use OCA\Berichtsheft\Service\FileStorageService;
use OCA\Berichtsheft\Service\LogoService;
use OCA\Berichtsheft\Service\PdfExportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * HTML-"Snapshot"-Tests fuer die Wochen-Vorlage (Plan Abschnitt 7): statt
 * einer echten Bildvergleichs-Snapshot-Datei (dompdf-Rendering ist
 * plattformabhaengig, s. Bug-Historie mit der doppelten CSS-Klasse) wird
 * hier die von PdfExportService::renderWoche() erzeugte HTML-Zwischenstufe
 * auf die entscheidenden Strukturmerkmale geprueft: Escaping, Fach-Format,
 * Sonderlabel, Sa/So-Zeilenausblendung, eindeutige CSS-Klasse pro Woche
 * (Regressionsschutz fuer den zuvor gefundenen Leerseiten-Bug).
 */
final class PdfExportServiceTest extends TestCase {
	private EintragMapper&MockObject $eintragMapper;
	private FachEintragMapper&MockObject $fachEintragMapper;
	private FachMapper&MockObject $fachMapper;
	private EintragService&MockObject $eintragService;
	private FileStorageService&MockObject $fileStorageService;
	private LogoService&MockObject $logoService;
	private PdfExportService $service;
	private string $appPath;

	protected function setUp(): void {
		$this->eintragMapper = $this->createMock(EintragMapper::class);
		$this->fachEintragMapper = $this->createMock(FachEintragMapper::class);
		$this->fachMapper = $this->createMock(FachMapper::class);
		$this->eintragService = $this->createMock(EintragService::class);
		$this->eintragService->method('getAusbildungsjahr')->willReturn(1);
		$this->fileStorageService = $this->createMock(FileStorageService::class);
		$this->logoService = $this->createMock(LogoService::class);
		$this->logoService->method('getLogoDataUri')->willReturn(null);

		$userManager = $this->createMock(\OCP\IUserManager::class);

		$this->service = new PdfExportService(
			$this->eintragMapper,
			$this->fachEintragMapper,
			$this->fachMapper,
			$this->eintragService,
			$this->fileStorageService,
			$this->logoService,
			$userManager,
		);

		$this->appPath = dirname(__DIR__, 3);
	}

	private function azubi(): Azubi {
		$azubi = new Azubi();
		$azubi->setId(1);
		$azubi->setUserId('azubi1');
		$azubi->setAusbildungsberuf('fiae');
		$azubi->setAusbildungsstart('2026-01-05');
		$azubi->setAusbildungsjahrStartWert(1);
		return $azubi;
	}

	private function woche(int $nachweisNr = 1): Woche {
		$woche = new Woche();
		$woche->setId(1);
		$woche->setAzubiId(1);
		$woche->setNachweisNr($nachweisNr);
		$woche->setWocheVon('2026-07-13');
		$woche->setWocheBis('2026-07-19');
		$woche->setStatus(Woche::STATUS_AKZEPTIERT);
		$woche->setEingereichtVonName('ein SIMBEL');
		$woche->setEingereichtAm(strtotime('2026-07-19 12:00'));
		$woche->setAkzeptiertVonName('ausbilder1');
		$woche->setAkzeptiertAm(strtotime('2026-07-19 13:00'));
		$woche->setCreatedAt(time());
		return $woche;
	}

	public function testBetriebstagZeigtEscapteTaetigkeitUndStunden(): void {
		$eintrag = new Eintrag();
		$eintrag->setAzubiId(1);
		$eintrag->setDatum('2026-07-13');
		$eintrag->setTagTyp(Eintrag::TAG_TYP_BETRIEB);
		$eintrag->setTaetigkeit('<script>alert(1)</script> Serverwartung');
		$eintrag->setStunden(8.0);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([$eintrag]);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringNotContainsString('<script>', $html);
		self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; Serverwartung', $html);
		self::assertStringContainsString('>8</td>', $html);
	}

	public function testBerufsschultagZeigtFachStundenUndInhaltMitGedankenstrich(): void {
		$eintrag = new Eintrag();
		$eintrag->setId(5);
		$eintrag->setAzubiId(1);
		$eintrag->setDatum('2026-07-14');
		$eintrag->setTagTyp(Eintrag::TAG_TYP_BERUFSSCHULE);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([$eintrag]);

		$fachEintrag = new FachEintrag();
		$fachEintrag->setEintragId(5);
		$fachEintrag->setFachId(2);
		$fachEintrag->setStunden(4.0);
		$fachEintrag->setInhalt('Grammatik: indirekte Rede');
		$this->fachEintragMapper->method('findByEintragId')->with(5)->willReturn([$fachEintrag]);

		$fach = new Fach();
		$fach->setId(2);
		$fach->setName('Deutsch');
		$this->fachMapper->method('find')->with(2)->willReturn($fach);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringContainsString('Deutsch: 4h – Grammatik: indirekte Rede', $html);
	}

	public function testFachEintragOhneInhaltZeigtNurFachUndStunden(): void {
		$eintrag = new Eintrag();
		$eintrag->setId(6);
		$eintrag->setAzubiId(1);
		$eintrag->setDatum('2026-07-14');
		$eintrag->setTagTyp(Eintrag::TAG_TYP_BERUFSSCHULE);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([$eintrag]);

		$fachEintrag = new FachEintrag();
		$fachEintrag->setEintragId(6);
		$fachEintrag->setFachId(3);
		$fachEintrag->setStunden(2.0);
		$this->fachEintragMapper->method('findByEintragId')->willReturn([$fachEintrag]);

		$fach = new Fach();
		$fach->setId(3);
		$fach->setName('BP');
		$this->fachMapper->method('find')->willReturn($fach);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringContainsString('BP: 2h', $html);
		self::assertStringNotContainsString('BP: 2h –', $html);
	}

	public function testSonderlabelFuerFeiertagUrlaubKrankheit(): void {
		$feiertag = new Eintrag();
		$feiertag->setAzubiId(1);
		$feiertag->setDatum('2026-07-15');
		$feiertag->setTagTyp(Eintrag::TAG_TYP_FEIERTAG);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([$feiertag]);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringContainsString('Feiertag', $html);
	}

	public function testSamstagUndSonntagOhneEintragWerdenNichtGedruckt(): void {
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([]);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringNotContainsString('>Samstag<', $html);
		self::assertStringNotContainsString('>Sonntag<', $html);
		// Mo-Fr bleiben als leere Zeilen sichtbar (kein Eintrag != kein Tag).
		self::assertStringContainsString('>Montag<', $html);
		self::assertStringContainsString('>Freitag<', $html);
	}

	public function testSeitenumbruchNurWennAngefordert(): void {
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([]);

		$mitUmbruch = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', true);
		$ohneUmbruch = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringContainsString('page-break-before: always', $mitUmbruch);
		self::assertStringNotContainsString('page-break-before: always', $ohneUmbruch);
	}

	public function testJedeWocheBekommtEineEindeutigeCssKlasseProNachweisNr(): void {
		// Regressionsschutz: alle vier Wochen-Fragmente eines Exports teilten
		// sich frueher dieselbe Klasse ".nachweis", wodurch die CSS-Kaskade
		// beim Zusammenfuegen zu einem Dokument nur die zuletzt deklarierte
		// Regel fuer ALLE Fragmente anwandte (Ursache einer leeren PDF-Seite).
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([]);

		$woche2 = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(2), 'SIMBEL ein', false);
		$woche5 = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(5), 'SIMBEL ein', false);

		self::assertStringContainsString('nachweis-2', $woche2);
		self::assertStringNotContainsString('nachweis-5', $woche2);
		self::assertStringContainsString('nachweis-5', $woche5);
		self::assertStringNotContainsString('nachweis-2', $woche5);
	}

	public function testZeitstempelUndNamenErscheinenStattUnterschriften(): void {
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([]);

		$html = $this->service->renderWoche($this->appPath, $this->azubi(), $this->woche(), 'SIMBEL ein', false);

		self::assertStringContainsString('Eingereicht von: ein SIMBEL am 19.07.2026 12:00', $html);
		self::assertStringContainsString('Geprüft/akzeptiert von: ausbilder1 am 19.07.2026 13:00', $html);
	}
}
