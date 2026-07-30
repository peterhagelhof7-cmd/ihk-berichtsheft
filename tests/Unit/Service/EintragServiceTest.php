<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Tests\Unit\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachLehrjahrMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisung;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCA\Berichtsheft\Db\Woche;
use OCA\Berichtsheft\Db\WocheMapper;
use OCA\Berichtsheft\Service\EintragService;
use OCA\Berichtsheft\Service\MailService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Deckt Plan Abschnitt 7 ("PHPUnit-Tests fuer EintragService-Validierung
 * inkl. Vollstaendigkeitspruefung vor Einreichung, ... den
 * Statusuebergaengen offen->eingereicht->zurueckgewiesen->eingereicht->
 * akzeptiert") ab. Reine Unit-Tests gegen gemockte Mapper/OCP-Abhaengig-
 * keiten - keine echte Datenbank noetig.
 */
final class EintragServiceTest extends TestCase {
	private EintragMapper&MockObject $eintragMapper;
	private FachEintragMapper&MockObject $fachEintragMapper;
	private FachMapper&MockObject $fachMapper;
	private FachLehrjahrMapper&MockObject $fachLehrjahrMapper;
	private LehrjahrZuweisungMapper&MockObject $lehrjahrZuweisungMapper;
	private WocheMapper&MockObject $wocheMapper;
	private INotificationManager&MockObject $notificationManager;
	private MailService&MockObject $mailService;
	private EintragService $service;

	protected function setUp(): void {
		$this->eintragMapper = $this->createMock(EintragMapper::class);
		$this->fachEintragMapper = $this->createMock(FachEintragMapper::class);
		$this->fachMapper = $this->createMock(FachMapper::class);
		$this->fachLehrjahrMapper = $this->createMock(FachLehrjahrMapper::class);
		$this->lehrjahrZuweisungMapper = $this->createMock(LehrjahrZuweisungMapper::class);
		$this->wocheMapper = $this->createMock(WocheMapper::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->mailService = $this->createMock(MailService::class);

		// Fluent-Aufrufkette createNotification()->setApp()->setUser()->... -> notify()
		// wird von einreichen() beim Erfolgspfad ausgeloest.
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->service = new EintragService(
			$this->eintragMapper,
			$this->fachEintragMapper,
			$this->fachMapper,
			$this->fachLehrjahrMapper,
			$this->lehrjahrZuweisungMapper,
			$this->wocheMapper,
			$this->notificationManager,
			$this->mailService,
		);
	}

	private function azubi(int $id = 1): Azubi {
		$azubi = new Azubi();
		$azubi->setId($id);
		$azubi->setUserId('azubi1');
		$azubi->setAusbildungsberuf('fiae');
		$azubi->setAusbildungsstart('2026-01-05');
		$azubi->setAusbildungsjahrStartWert(1);
		$azubi->setVerantwortlicherAusbilderUserId('ausbilder1');
		return $azubi;
	}

	private function woche(string $status, string $wocheVon = '2026-07-13'): Woche {
		$woche = new Woche();
		$woche->setId(1);
		$woche->setAzubiId(1);
		$woche->setNachweisNr(1);
		$woche->setWocheVon($wocheVon);
		$woche->setWocheBis(EintragService::wocheBisFuer($wocheVon));
		$woche->setStatus($status);
		$woche->setCreatedAt(time());
		return $woche;
	}

	// -- wocheVonFuer/wocheBisFuer -----------------------------------

	public function testWocheVonFuerLiefertDenMontagDerWoche(): void {
		// Donnerstag der Woche -> Montag derselben Woche.
		self::assertSame('2026-07-13', EintragService::wocheVonFuer('2026-07-16'));
		// Bereits ein Montag -> unveraendert.
		self::assertSame('2026-07-13', EintragService::wocheVonFuer('2026-07-13'));
		// Sonntag -> Montag derselben (vorangehenden) Woche.
		self::assertSame('2026-07-13', EintragService::wocheVonFuer('2026-07-19'));
	}

	public function testWocheBisFuerLiefertDenSonntagSechsTageSpaeter(): void {
		self::assertSame('2026-07-19', EintragService::wocheBisFuer('2026-07-13'));
	}

	// -- nachweisNrFuer (Bugfix: kalendarischer Abstand statt Anlage-Reihenfolge) --

	public function testNachweisNrFuerLiefertEinsFuerDieStartwoche(): void {
		$azubi = $this->azubi();
		$azubi->setAusbildungsstart('2026-01-05'); // ist bereits ein Montag

		self::assertSame(1, EintragService::nachweisNrFuer($azubi, '2026-01-05'));
	}

	public function testNachweisNrFuerZaehltProKalenderwocheEins(): void {
		$azubi = $this->azubi();
		$azubi->setAusbildungsstart('2026-01-05');

		self::assertSame(1, EintragService::nachweisNrFuer($azubi, '2026-01-05'));
		self::assertSame(2, EintragService::nachweisNrFuer($azubi, '2026-01-12'));
		self::assertSame(3, EintragService::nachweisNrFuer($azubi, '2026-01-19'));
	}

	/**
	 * Der eigentliche Bug-Report: beginnt der Azubi seine Ausbildung so,
	 * dass KW40/2025 kalendarisch die 5. Woche seit Ausbildungsstart ist,
	 * MUSS diese Woche immer Nachweis Nr. 5 werden - unabhaengig davon, in
	 * welcher Reihenfolge der Azubi Wochen ausfuellt. Vorher (Bug): einfach
	 * "letzte vergebene Nummer + 1", d.h. fuellte er KW40 als allererstes
	 * aus, waere sie faelschlich Nachweis 1 (bzw. 2, wenn schon eine andere
	 * Woche existierte) geworden.
	 */
	public function testNachweisNrFuerIstUnabhaengigVonDerAusfuellReihenfolge(): void {
		$azubi = $this->azubi();
		// Start-Woche = Montag der Kalenderwoche, die 4 Wochen vor KW40/2025 liegt.
		$startWoche = (new \DateTimeImmutable())->setISODate(2025, 40, 1)->sub(new \DateInterval('P28D'));
		$azubi->setAusbildungsstart($startWoche->format('Y-m-d'));

		$kw40Montag = (new \DateTimeImmutable())->setISODate(2025, 40, 1)->format('Y-m-d');

		// KW40 zuerst (und einzig) abgefragt - muss trotzdem Nachweis 5 sein,
		// nicht 1 oder 2, nur weil sie zuerst drankam.
		self::assertSame(5, EintragService::nachweisNrFuer($azubi, $kw40Montag));

		// Reihenfolge spielt keine Rolle: dieselbe Berechnung liefert fuer
		// eine spaeter abgefragte fruehere Woche trotzdem die kleinere Nummer.
		self::assertSame(1, EintragService::nachweisNrFuer($azubi, $startWoche->format('Y-m-d')));
	}

	public function testNachweisNrFuerFaengtWochenVorDemAusbildungsstartAufNachweis1Ab(): void {
		$azubi = $this->azubi();
		$azubi->setAusbildungsstart('2026-03-02'); // Montag

		// Eine Woche VOR dem Ausbildungsstart - sollte im Normalbetrieb nicht
		// vorkommen, darf aber keine negative/0-Nummer erzeugen.
		self::assertSame(1, EintragService::nachweisNrFuer($azubi, '2026-02-23'));
	}

	/**
	 * Testet den Bug-Report ueber den vollen Weg (getOderErstelleWoche, wie
	 * ihn EintragService::speichereEintrag tatsaechlich aufruft) statt nur
	 * die reine Berechnung - stellt sicher, dass eine NEU angelegte Woche
	 * tatsaechlich die kalendarische, nicht die anlage-reihenfolge-basierte
	 * Nummer bekommt.
	 */
	public function testGetOderErstelleWocheVergibtKalenderbasierteNummerFuerNeueWoche(): void {
		$azubi = $this->azubi();
		$startWoche = (new \DateTimeImmutable())->setISODate(2025, 40, 1)->sub(new \DateInterval('P28D'));
		$azubi->setAusbildungsstart($startWoche->format('Y-m-d'));
		$kw40Montag = (new \DateTimeImmutable())->setISODate(2025, 40, 1)->format('Y-m-d');

		$this->wocheMapper->method('findByAzubiAndWocheVon')->willThrowException(new DoesNotExistException(''));
		$eingefuegteWoche = null;
		$this->wocheMapper->method('insert')->willReturnCallback(function (Woche $w) use (&$eingefuegteWoche) {
			$eingefuegteWoche = $w;
			$w->setId(1);
			return $w;
		});

		$this->service->getOderErstelleWoche($azubi, $kw40Montag);

		self::assertSame(5, $eingefuegteWoche->getNachweisNr());
	}

	// -- pruefeBearbeitbar --------------------------------------------

	public function testPruefeBearbeitbarErlaubtOffenUndZurueckgewiesen(): void {
		$this->service->pruefeBearbeitbar($this->woche(Woche::STATUS_OFFEN));
		$this->service->pruefeBearbeitbar($this->woche(Woche::STATUS_ZURUECKGEWIESEN));
		$this->addToAssertionCount(2); // kein Throw = bestanden
	}

	public function testPruefeBearbeitbarBlockiertEingereicht(): void {
		$this->expectException(\DomainException::class);
		$this->service->pruefeBearbeitbar($this->woche(Woche::STATUS_EINGEREICHT));
	}

	public function testPruefeBearbeitbarBlockiertAkzeptiertDauerhaft(): void {
		$this->expectException(\DomainException::class);
		$this->service->pruefeBearbeitbar($this->woche(Woche::STATUS_AKZEPTIERT));
	}

	// -- getAusbildungsjahr ---------------------------------------------

	public function testGetAusbildungsjahrBleibtImErstenJahrKonstant(): void {
		$azubi = $this->azubi();
		self::assertSame(1, $this->service->getAusbildungsjahr($azubi, '2026-07-13'));
	}

	public function testGetAusbildungsjahrBeruecksichtigtStartwertFuerBetriebswechsler(): void {
		$azubi = $this->azubi();
		$azubi->setAusbildungsjahrStartWert(2);
		$azubi->setAusbildungsstart('2026-10-01');
		self::assertSame(2, $this->service->getAusbildungsjahr($azubi, '2026-10-05'));
	}

	// -- getVerfuegbareFaecher --------------------------------------

	public function testGetVerfuegbareFaecherFiltertNachAktuellemLehrjahr(): void {
		$zuweisung = new LehrjahrZuweisung();
		$zuweisung->setLehrjahr(2);
		$this->lehrjahrZuweisungMapper->method('findAktuellFuerAzubi')->willReturn($zuweisung);
		$this->fachLehrjahrMapper->method('findFachIdsByLehrjahr')->with(2)->willReturn([1, 3]);

		$deutsch = new Fach();
		$deutsch->setId(1);
		$deutsch->setName('Deutsch');
		$systeme = new Fach();
		$systeme->setId(2);
		$systeme->setName('Systeme'); // nur Lehrjahr 4, darf hier NICHT erscheinen
		$bp = new Fach();
		$bp->setId(3);
		$bp->setName('BP');
		$this->fachMapper->method('findAll')->willReturn([$deutsch, $systeme, $bp]);

		$ergebnis = $this->service->getVerfuegbareFaecher($this->azubi(), '2026-07-13');

		self::assertCount(2, $ergebnis);
		self::assertSame(['Deutsch', 'BP'], array_map(static fn (Fach $f) => $f->getName(), $ergebnis));
	}

	public function testGetVerfuegbareFaecherLiefertLeeresArrayOhneZuweisung(): void {
		$this->lehrjahrZuweisungMapper->method('findAktuellFuerAzubi')
			->willThrowException(new DoesNotExistException(''));

		self::assertSame([], $this->service->getVerfuegbareFaecher($this->azubi(), '2026-07-13'));
	}

	// -- istVollstaendig / Vollstaendigkeitspruefung vor Einreichung ---

	public function testIstVollstaendigWennMontagBisFreitagErfasstSind(): void {
		$eintraege = array_map([$this, 'eintrag'], ['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17']);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($eintraege);

		self::assertTrue($this->service->istVollstaendig($this->azubi(), '2026-07-13', false, false));
	}

	public function testIstVollstaendigSchlaegtFehlWennEinTagFehlt(): void {
		// Donnerstag fehlt.
		$eintraege = array_map([$this, 'eintrag'], ['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-17']);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($eintraege);

		self::assertFalse($this->service->istVollstaendig($this->azubi(), '2026-07-13', false, false));
	}

	public function testIstVollstaendigVerlangtSamstagNurWennZugeschaltet(): void {
		$montagBisFreitag = array_map([$this, 'eintrag'], ['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17']);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($montagBisFreitag);

		self::assertTrue($this->service->istVollstaendig($this->azubi(), '2026-07-13', false, false));
		self::assertFalse($this->service->istVollstaendig($this->azubi(), '2026-07-13', true, false));

		$mitSamstag = array_merge($montagBisFreitag, [$this->eintrag('2026-07-18')]);
		$this->eintragMapper = $this->createMock(EintragMapper::class);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($mitSamstag);
		$service = new EintragService(
			$this->eintragMapper, $this->fachEintragMapper, $this->fachMapper,
			$this->fachLehrjahrMapper, $this->lehrjahrZuweisungMapper, $this->wocheMapper,
			$this->notificationManager, $this->mailService,
		);
		self::assertTrue($service->istVollstaendig($this->azubi(), '2026-07-13', true, false));
	}

	private function eintrag(string $datum): Eintrag {
		$eintrag = new Eintrag();
		$eintrag->setAzubiId(1);
		$eintrag->setDatum($datum);
		$eintrag->setTagTyp(Eintrag::TAG_TYP_BETRIEB);
		return $eintrag;
	}

	// -- einreichen: Statusuebergaenge --------------------------------
	// offen -> eingereicht -> zurueckgewiesen -> eingereicht -> akzeptiert
	// (der letzte Schritt - akzeptiert - liegt in PruefungController, hier
	// wird die EintragService-Seite der Kette getestet: einreichen() muss
	// sowohl aus "offen" als auch nach einer Zurueckweisung aus
	// "zurueckgewiesen" heraus erneut funktionieren, und pruefeBearbeitbar
	// muss den akzeptierten Endzustand dauerhaft sperren, s.o.)

	public function testEinreichenSchlaegtFehlWennUnvollstaendig(): void {
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn([]);
		$woche = $this->woche(Woche::STATUS_OFFEN);

		$this->expectException(\DomainException::class);
		$this->service->einreichen($this->azubi(), $woche, false, false);
	}

	public function testEinreichenAusOffenSetztStatusUndZeitstempel(): void {
		$eintraege = array_map([$this, 'eintrag'], ['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17']);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($eintraege);
		$this->wocheMapper->method('update')->willReturnArgument(0);

		$woche = $this->woche(Woche::STATUS_OFFEN);
		$ergebnis = $this->service->einreichen($this->azubi(), $woche, false, false);

		self::assertSame(Woche::STATUS_EINGEREICHT, $ergebnis->getStatus());
		self::assertSame('azubi1', $ergebnis->getEingereichtVonUserId());
		self::assertNotNull($ergebnis->getEingereichtAm());
	}

	public function testEinreichenAusZurueckgewiesenFunktioniertErneut(): void {
		$eintraege = array_map([$this, 'eintrag'], ['2026-07-13', '2026-07-14', '2026-07-15', '2026-07-16', '2026-07-17']);
		$this->eintragMapper->method('findByAzubiAndDateRange')->willReturn($eintraege);
		$this->wocheMapper->method('update')->willReturnArgument(0);

		$woche = $this->woche(Woche::STATUS_ZURUECKGEWIESEN);
		$ergebnis = $this->service->einreichen($this->azubi(), $woche, false, false);

		self::assertSame(Woche::STATUS_EINGEREICHT, $ergebnis->getStatus());
	}

	public function testEinreichenAusAkzeptiertIstDauerhaftGesperrt(): void {
		$woche = $this->woche(Woche::STATUS_AKZEPTIERT);

		$this->expectException(\DomainException::class);
		$this->service->einreichen($this->azubi(), $woche, false, false);
	}

	// -- speichereEintrag: Bearbeitungssperre + Fach-Zeilen -------------

	public function testSpeichereEintragBlockiertBeiEingereichterWoche(): void {
		$this->wocheMapper->method('findByAzubiAndWocheVon')->willReturn($this->woche(Woche::STATUS_EINGEREICHT));

		$this->expectException(\DomainException::class);
		$this->service->speichereEintrag($this->azubi(), '2026-07-14', 'berufsschule', null, null, []);
	}

	public function testSpeichereEintragLegtFachZeilenFuerBerufsschuleAn(): void {
		$this->wocheMapper->method('findByAzubiAndWocheVon')->willReturn($this->woche(Woche::STATUS_OFFEN));
		$this->eintragMapper->method('findByAzubiAndDatum')->willThrowException(new DoesNotExistException(''));
		$this->eintragMapper->method('insert')->willReturnCallback(static function (Eintrag $e) {
			$e->setId(42);
			return $e;
		});

		$this->fachEintragMapper->expects(self::once())->method('deleteByEintragId')->with(42);
		$this->fachEintragMapper->expects(self::exactly(2))->method('insert');

		$this->service->speichereEintrag($this->azubi(), '2026-07-14', 'berufsschule', null, null, [
			['fachId' => 1, 'stunden' => 2.0, 'inhalt' => 'Grammatik'],
			['fachId' => 2, 'stunden' => 2.0],
		]);
	}

	public function testSpeichereEintragSpeichertKeineFachzeilenFuerBetrieb(): void {
		$this->wocheMapper->method('findByAzubiAndWocheVon')->willReturn($this->woche(Woche::STATUS_OFFEN));
		$this->eintragMapper->method('findByAzubiAndDatum')->willThrowException(new DoesNotExistException(''));
		$this->eintragMapper->method('insert')->willReturnCallback(static function (Eintrag $e) {
			$e->setId(1);
			return $e;
		});

		$this->fachEintragMapper->expects(self::never())->method('insert');

		$eintrag = $this->service->speichereEintrag($this->azubi(), '2026-07-13', 'betrieb', 'Serverwartung', 8.0, []);

		self::assertSame('Serverwartung', $eintrag->getTaetigkeit());
		self::assertSame(8.0, $eintrag->getStunden());
	}
}
