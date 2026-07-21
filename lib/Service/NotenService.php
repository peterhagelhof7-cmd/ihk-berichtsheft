<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use DateInterval;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Fach;
use OCA\Berichtsheft\Db\FachEintrag;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachLehrjahrMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\LehrjahrZuweisung;
use OCA\Berichtsheft\Db\LehrjahrZuweisungMapper;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;
use OCP\Server;

/**
 * Notenverwaltung (Notenstand je Azubi/Lehrjahr): pro Fach des aktuell
 * gueltigen Lehrjahrs eine Liste der einzelnen Noten (an Berufsschultagen
 * ueber FachZeile erfasst, s. EintragService/bh_fach_eintrag) plus deren
 * gewichteter Notenschnitt (muendlich/stehgreif zaehlen nur halb, s.
 * FachEintrag::NOTE_GEWICHT). Es gibt bewusst KEINE eigene, mutable
 * "Notentabelle" in der Datenbank - die Tabelle wird bei jeder Abfrage aus
 * den Tageseintraegen des aktuellen Lehrjahr-Zeitraums (gueltig_ab der
 * LehrjahrZuweisung bis heute/Lehrjahrende) neu zusammengestellt. "Eine
 * neue Tabelle beginnt" beim Lehrjahrwechsel deshalb ganz von selbst -
 * archiviereLehrjahrende() haelt nur den Endstand des ablaufenden
 * Lehrjahrs als PDF fest, bevor der Zeitraum-Filter auf das neue Lehrjahr
 * umspringt.
 */
class NotenService {
	public function __construct(
		private EintragMapper $eintragMapper,
		private FachEintragMapper $fachEintragMapper,
		private FachMapper $fachMapper,
		private FachLehrjahrMapper $fachLehrjahrMapper,
		private LehrjahrZuweisungMapper $lehrjahrZuweisungMapper,
		private FileStorageService $fileStorageService,
		private IUserManager $userManager,
	) {
	}

	/**
	 * Notentabelle des aktuell (noch laufenden) Lehrjahrs - offenes Ende.
	 * @throws DoesNotExistException wenn dem Azubi noch kein Lehrjahr zugewiesen ist
	 */
	public function aktuelleTabelle(Azubi $azubi): array {
		$heute = date('Y-m-d');
		$zuweisung = $this->lehrjahrZuweisungMapper->findAktuellFuerAzubi($azubi->getId(), $heute);
		$eintraege = $this->eintragMapper->findByAzubiVonDatum($azubi->getId(), $zuweisung->getGueltigAb());

		return [
			'lehrjahr' => $zuweisung->getLehrjahr(),
			'gueltigAb' => $zuweisung->getGueltigAb(),
			'faecher' => $this->faecherTabelle($zuweisung->getLehrjahr(), $eintraege),
		];
	}

	/** @param Eintrag[] $eintraege */
	private function faecherTabelle(int $lehrjahr, array $eintraege): array {
		$fachIds = $this->fachLehrjahrMapper->findFachIdsByLehrjahr($lehrjahr);
		$faecher = array_values(array_filter(
			$this->fachMapper->findAll(),
			static fn (Fach $f) => in_array($f->getId(), $fachIds, true),
		));

		/** @var array<int, array<array{datum:string,art:string,note:int,gewicht:float}>> $notenProFach */
		$notenProFach = [];
		foreach ($eintraege as $eintrag) {
			if ($eintrag->getTagTyp() !== Eintrag::TAG_TYP_BERUFSSCHULE) {
				continue;
			}
			foreach ($this->fachEintragMapper->findByEintragId($eintrag->getId()) as $fachEintrag) {
				if ($fachEintrag->getNoteArt() === null) {
					continue;
				}
				$notenProFach[$fachEintrag->getFachId()][] = [
					'datum' => $eintrag->getDatum(),
					'art' => $fachEintrag->getNoteArt(),
					'note' => $fachEintrag->getNote(),
					'gewicht' => FachEintrag::NOTE_GEWICHT[$fachEintrag->getNoteArt()],
				];
			}
		}

		return array_map(static function (Fach $fach) use ($notenProFach) {
			$noten = $notenProFach[$fach->getId()] ?? [];
			return [
				'fachId' => $fach->getId(),
				'fachName' => $fach->getName(),
				'noten' => $noten,
				'schnitt' => self::schnitt($noten),
			];
		}, $faecher);
	}

	/** @param array<array{note:int,gewicht:float}> $noten */
	private static function schnitt(array $noten): ?float {
		if (count($noten) === 0) {
			return null;
		}
		$summeGewichtet = 0.0;
		$summeGewicht = 0.0;
		foreach ($noten as $n) {
			$summeGewichtet += $n['note'] * $n['gewicht'];
			$summeGewicht += $n['gewicht'];
		}
		return $summeGewicht > 0 ? round($summeGewichtet / $summeGewicht, 2) : null;
	}

	/**
	 * Haelt den Notenschnitt-Endstand des GERADE ABLAUFENDEN Lehrjahrs als
	 * PDF im Berichtsheft-Ordner des Azubis fest - aufgerufen, wenn eine
	 * neue LehrjahrZuweisung angelegt wird, die eine bestehende ablaest
	 * (LehrjahrController::create()). $endendeZuweisung ist die bis dahin
	 * gueltige Zuweisung, $neuesGueltigAb der Stichtag der neuen.
	 */
	public function archiviereLehrjahrende(Azubi $azubi, LehrjahrZuweisung $endendeZuweisung, string $neuesGueltigAb): void {
		$bisInklusiv = (new DateTimeImmutable($neuesGueltigAb))->sub(new DateInterval('P1D'))->format('Y-m-d');
		$eintraege = $this->eintragMapper->findByAzubiAndDateRange($azubi->getId(), $endendeZuweisung->getGueltigAb(), $bisInklusiv);
		$faecher = $this->faecherTabelle($endendeZuweisung->getLehrjahr(), $eintraege);

		$dateiname = sprintf('%s - Notenschnitt Lehrjahr %d.pdf', PdfExportService::azubiDateinameBasis($azubi, $this->userManager), $endendeZuweisung->getLehrjahr());
		$this->renderUndSpeichere(
			$azubi,
			$endendeZuweisung->getLehrjahr(),
			$endendeZuweisung->getGueltigAb(),
			$bisInklusiv,
			$faecher,
			$dateiname,
		);
	}

	/**
	 * Aktuelle (laufende) Notentabelle als PDF, ueberschreibt jeweils die
	 * vorherige Fassung unter demselben festen Dateinamen - so hat der
	 * Azubi jederzeit eine aktuelle Uebersicht im Dateibereich, nicht nur
	 * beim Lehrjahrende. Wird von ExportGeneratorJob nach jedem regulaeren
	 * 4-Wochen-Export mit ausgeloest, damit beide PDFs im selben Rhythmus
	 * aktuell bleiben.
	 * @throws DoesNotExistException wenn dem Azubi noch kein Lehrjahr zugewiesen ist
	 */
	public function aktualisiereAktuelleUebersicht(Azubi $azubi): void {
		$tabelle = $this->aktuelleTabelle($azubi);
		$dateiname = sprintf('%s - Notenschnitt aktuell.pdf', PdfExportService::azubiDateinameBasis($azubi, $this->userManager));
		$this->renderUndSpeichere(
			$azubi,
			$tabelle['lehrjahr'],
			$tabelle['gueltigAb'],
			date('Y-m-d'),
			$tabelle['faecher'],
			$dateiname,
		);
	}

	private function renderUndSpeichere(Azubi $azubi, int $lehrjahr, string $von, string $bis, array $faecher, string $dateiname): void {
		$appPath = Server::get(IAppManager::class)->getAppPath(Application::APP_ID);
		$html = $this->renderTemplate($appPath . '/templates/pdf/notenschnitt.php', [
			'azubiName' => PdfExportService::azubiDateinameBasis($azubi, $this->userManager),
			'lehrjahr' => $lehrjahr,
			'zeitraumVonFormatiert' => (new DateTimeImmutable($von))->format('d.m.Y'),
			'zeitraumBisFormatiert' => (new DateTimeImmutable($bis))->format('d.m.Y'),
			'faecher' => $faecher,
		]);

		$options = new DompdfOptions();
		$options->setIsRemoteEnabled(false);
		$dompdf = new Dompdf($options);
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4');
		$dompdf->render();

		$this->fileStorageService->speicherePdf($azubi, $dateiname, $dompdf->output());
	}

	private function renderTemplate(string $pfad, array $vars): string {
		extract($vars, EXTR_SKIP);
		ob_start();
		require $pfad;
		return (string)ob_get_clean();
	}
}
