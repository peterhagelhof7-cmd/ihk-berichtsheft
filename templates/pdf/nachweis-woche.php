<?php

declare(strict_types=1);

/**
 * Erwartete Variablen: $nachweisNr, $azubiName, $ausbildungsabteilung,
 * $ausbildungsjahr, $wocheVonFormatiert, $wocheBisFormatiert, $tage (Liste
 * von ['label'=>string,'datum'=>string,'inhaltHtml'=>string bereits
 * escaped/formatiert,'stundenText'=>string]), $eingereichtVonName,
 * $eingereichtAmFormatiert, $akzeptiertVonName, $akzeptiertAmFormatiert,
 * $bemerkungen, $seitenumbruchDavor (bool). Wird von PdfExportService per
 * extract() befuellt (Plan Abschnitt 1/4) - HTML-Aufbereitung der
 * Tagesinhalte (Freitext vs. Fach-Liste vs. Sonderlabel
 * Feiertag/Urlaub/Krankheit) passiert bereits im PHP-Code, nicht hier.
 *
 * Diese Vorlage wird pro Woche einzeln gerendert und die Ergebnisse
 * anschliessend zu EINEM HTML-Dokument aneinandergehaengt
 * (PdfExportService::erzeugeExport). Deshalb braucht jede Instanz eine
 * eindeutige CSS-Klasse statt der frueheren gemeinsamen ".nachweis" -
 * sonst gilt in der finalen Kaskade nur die zuletzt deklarierte Regel fuer
 * ALLE vier Bloecke gleichzeitig (u.a. Ursache einer leeren Extra-Seite).
 */
$e = static fn (?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$klasse = 'nachweis-' . (int)$nachweisNr;

?>
<style>
	html, body { margin: 0; padding: 0; }
	.<?= $klasse ?> { font-family: sans-serif; font-size: 9pt; padding: 10mm; <?= $seitenumbruchDavor ? 'page-break-before: always;' : '' ?> }
	.<?= $klasse ?> .kopf { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
	.<?= $klasse ?> .kopf td { padding: 1mm 2mm; font-size: 10pt; }
	.<?= $klasse ?> .kopf td.label { font-weight: bold; width: 30%; }
	.<?= $klasse ?> table.tage { width: 100%; border-collapse: collapse; }
	.<?= $klasse ?> table.tage th, .<?= $klasse ?> table.tage td { border: 0.3mm solid #666; padding: 2mm; vertical-align: top; }
	.<?= $klasse ?> table.tage th { background: #eee; font-size: 8pt; }
	.<?= $klasse ?> table.tage td.tag-label { width: 15%; font-weight: bold; }
	.<?= $klasse ?> table.tage td.stunden { width: 12%; text-align: right; }
	.<?= $klasse ?> .fuss { margin-top: 4mm; font-size: 9pt; }
	.<?= $klasse ?> .fuss table { width: 100%; }
	.<?= $klasse ?> .fuss td { padding: 1mm 2mm; vertical-align: top; }
</style>

<div class="<?= $klasse ?>">
	<table class="kopf">
		<tr>
			<td class="label">Ausbildungsnachweis Nr.</td><td><?= $e((string)$nachweisNr) ?></td>
			<td class="label">Name:</td><td><?= $e($azubiName) ?></td>
		</tr>
		<tr>
			<td class="label">Ausbildungswoche vom</td><td><?= $e($wocheVonFormatiert) ?> bis <?= $e($wocheBisFormatiert) ?></td>
			<td class="label">Ausbildungsabteilung:</td><td><?= $e($ausbildungsabteilung) ?></td>
		</tr>
		<tr>
			<td class="label">Ausbildungsjahr</td><td colspan="3"><?= $e((string)$ausbildungsjahr) ?></td>
		</tr>
	</table>

	<table class="tage">
		<tr>
			<th>Tag</th>
			<th>Ausgeführte Arbeiten, Unterweisungen, betrieblicher Unterricht, usw.</th>
			<th>Einzel-stunden</th>
		</tr>
		<?php foreach ($tage as $tag): ?>
		<tr>
			<td class="tag-label"><?= $e($tag['label']) ?></td>
			<td><?= $tag['inhaltHtml'] /* bereits escaped */ ?></td>
			<td class="stunden"><?= $e($tag['stundenText']) ?></td>
		</tr>
		<?php endforeach; ?>
	</table>

	<div class="fuss">
		<table>
			<tr>
				<td>Eingereicht von: <?= $e($eingereichtVonName) ?> am <?= $e($eingereichtAmFormatiert) ?></td>
			</tr>
			<tr>
				<td>Geprüft/akzeptiert von: <?= $e($akzeptiertVonName) ?> am <?= $e($akzeptiertAmFormatiert) ?></td>
			</tr>
			<tr>
				<td>Bemerkungen: <?= $e($bemerkungen) ?></td>
			</tr>
		</table>
	</div>
</div>
