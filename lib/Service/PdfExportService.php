<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use OCA\Berichtsheft\AppInfo\Application;
use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Eintrag;
use OCA\Berichtsheft\Db\EintragMapper;
use OCA\Berichtsheft\Db\Export;
use OCA\Berichtsheft\Db\FachEintragMapper;
use OCA\Berichtsheft\Db\FachMapper;
use OCA\Berichtsheft\Db\Woche;
use OCP\App\IAppManager;
use OCP\IUserManager;
use OCP\Server;

/**
 * Rendert das wiederkehrende 4-Wochen-PDF (Plan Abschnitt 3/4, Job
 * ExportGeneratorJob) - OHNE Deckblatt (das macht DeckblattService
 * separat, einmalig pro Azubi). Dateiname:
 * "<Nachname> <Vorname>-<NN> - KW<WW>-<JJJJ>.pdf".
 */
class PdfExportService {
	private const TAGE_LABEL = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
	private const SONDERLABEL = [
		Eintrag::TAG_TYP_FEIERTAG => 'Feiertag',
		Eintrag::TAG_TYP_URLAUB => 'Urlaub',
		Eintrag::TAG_TYP_KRANKHEIT => 'Krankheit',
	];

	public function __construct(
		private EintragMapper $eintragMapper,
		private FachEintragMapper $fachEintragMapper,
		private FachMapper $fachMapper,
		private EintragService $eintragService,
		private FileStorageService $fileStorageService,
		private LogoService $logoService,
		private IUserManager $userManager,
	) {
	}

	public static function azubiDateinameBasis(Azubi $azubi, IUserManager $userManager): string {
		$nachname = trim($azubi->getNachname() ?? '');
		$vorname = trim($azubi->getVorname() ?? '');
		if ($nachname !== '' || $vorname !== '') {
			return trim("$nachname $vorname");
		}
		return $userManager->get($azubi->getUserId())?->getDisplayName() ?? $azubi->getUserId();
	}

	private function dateiname(Azubi $azubi, Export $export): string {
		$basis = self::azubiDateinameBasis($azubi, $this->userManager);
		$nn = str_pad((string)$export->getExportNr(), 2, '0', STR_PAD_LEFT);
		$kw = (new \DateTimeImmutable($export->getZeitraumVon()))->format('W');
		$jahr = (new \DateTimeImmutable($export->getZeitraumVon()))->format('Y');
		return "$basis-$nn - KW$kw-$jahr.pdf";
	}

	/** @param Woche[] $wochen genau 4, sortiert nach nachweis_nr (WocheMapper::findByExportId liefert das bereits) */
	public function erzeugeExport(Azubi $azubi, Export $export, array $wochen): void {
		$appPath = Server::get(IAppManager::class)->getAppPath(Application::APP_ID);
		$azubiName = self::azubiDateinameBasis($azubi, $this->userManager);

		$html = '';
		foreach ($wochen as $index => $woche) {
			// page-break-before (statt -after auf allen ausser der letzten
			// Sektion) - dompdf haengt bei "page-break-after: always" auf der
			// letzten Sektion sonst eine leere Extra-Seite ans PDF-Ende an.
			$html .= $this->renderWoche($appPath, $azubi, $woche, $azubiName, $index > 0);
		}

		$options = new DompdfOptions();
		$options->setIsRemoteEnabled(false);
		$dompdf = new Dompdf($options);
		// Encoding explizit: sonst raet dompdf und verschluckt sich am
		// base64-Logo-Data-URI (Fehldetektion -> alle Umlaute werden zu "?").
		$dompdf->loadHtml($html, 'UTF-8');
		$dompdf->setPaper('A4');
		$dompdf->render();

		$this->fileStorageService->speicherePdf($azubi, $this->dateiname($azubi, $export), $dompdf->output());
	}

	/** Oeffentlich, damit GesamtExportService dieselbe Wochen-Vorlage fuer den IHK-Gesamtnachweis wiederverwenden kann. */
	public function renderWoche(string $appPath, Azubi $azubi, Woche $woche, string $azubiName, bool $seitenumbruchDavor): string {
		$eintraege = $this->eintragMapper->findByAzubiAndDateRange($azubi->getId(), $woche->getWocheVon(), $woche->getWocheBis());
		$eintraegeByDatum = [];
		foreach ($eintraege as $eintrag) {
			$eintraegeByDatum[$eintrag->getDatum()] = $eintrag;
		}

		$tage = [];
		for ($i = 0; $i < 7; $i++) {
			$datum = (new \DateTimeImmutable($woche->getWocheVon()))->add(new \DateInterval("P{$i}D"))->format('Y-m-d');
			$eintrag = $eintraegeByDatum[$datum] ?? null;
			if ($eintrag === null) {
				if ($i >= 5) {
					continue; // Sa/So ohne Eintrag: Zeile entfaellt ersatzlos (Plan Abschnitt 1)
				}
				$tage[] = ['label' => self::TAGE_LABEL[$i], 'inhaltHtml' => '', 'stundenText' => ''];
				continue;
			}
			$tage[] = [
				'label' => self::TAGE_LABEL[$i],
				'inhaltHtml' => $this->formatiereInhalt($eintrag),
				'stundenText' => $eintrag->getStunden() !== null ? (string)$eintrag->getStunden() : '',
			];
		}

		$vars = [
			'nachweisNr' => $woche->getNachweisNr(),
			'azubiName' => $azubiName,
			'ausbildungsabteilung' => '',
			'ausbildungsjahr' => $this->eintragService->getAusbildungsjahr($azubi, $woche->getWocheVon()),
			'wocheVonFormatiert' => (new \DateTimeImmutable($woche->getWocheVon()))->format('d.m.Y'),
			'wocheBisFormatiert' => (new \DateTimeImmutable($woche->getWocheBis()))->format('d.m.Y'),
			'tage' => $tage,
			'eingereichtVonName' => $woche->getEingereichtVonName() ?? '',
			'eingereichtAmFormatiert' => $woche->getEingereichtAm() !== null ? date('d.m.Y H:i', $woche->getEingereichtAm()) : '',
			'akzeptiertVonName' => $woche->getAkzeptiertVonName() ?? '',
			'akzeptiertAmFormatiert' => $woche->getAkzeptiertAm() !== null ? date('d.m.Y H:i', $woche->getAkzeptiertAm()) : '',
			'bemerkungen' => $woche->getBemerkungen() ?? '',
			'seitenumbruchDavor' => $seitenumbruchDavor,
			'logoDataUri' => $this->logoService->getLogoDataUri(),
		];

		return $this->renderTemplate($appPath . '/templates/pdf/nachweis-woche.php', $vars);
	}

	private function formatiereInhalt(Eintrag $eintrag): string {
		if (isset(self::SONDERLABEL[$eintrag->getTagTyp()])) {
			return htmlspecialchars(self::SONDERLABEL[$eintrag->getTagTyp()], ENT_QUOTES, 'UTF-8');
		}
		if ($eintrag->getTagTyp() === Eintrag::TAG_TYP_BERUFSSCHULE) {
			$zeilen = [];
			foreach ($this->fachEintragMapper->findByEintragId($eintrag->getId()) as $fachEintrag) {
				$fach = null;
				try {
					$fach = $this->fachMapper->find($fachEintrag->getFachId());
				} catch (\Throwable) {
					// Fach zwischenzeitlich geloescht - Name entfaellt, Stunden bleiben sichtbar.
				}
				$zeile = ($fach?->getName() ?? '?') . ': ' . $fachEintrag->getStunden() . 'h';
				if ($fachEintrag->getInhalt() !== null) {
					$zeile .= ' – ' . $fachEintrag->getInhalt();
				}
				$zeilen[] = htmlspecialchars($zeile, ENT_QUOTES, 'UTF-8');
			}
			return implode('<br>', $zeilen);
		}
		// Betrieb: mehrzeiliger Freitext.
		return nl2br(htmlspecialchars($eintrag->getTaetigkeit() ?? '', ENT_QUOTES, 'UTF-8'));
	}

	private function renderTemplate(string $pfad, array $vars): string {
		extract($vars, EXTR_SKIP);
		ob_start();
		require $pfad;
		return (string)ob_get_clean();
	}
}
