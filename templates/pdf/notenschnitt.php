<?php

declare(strict_types=1);

/**
 * Erwartete Variablen: $azubiName, $lehrjahr (int), $zeitraumVonFormatiert,
 * $zeitraumBisFormatiert, $faecher (Liste von
 * ['fachName'=>string,'schnitt'=>?float,'noten'=>Liste von
 * ['datum'=>string,'art'=>string,'note'=>int,'gewicht'=>float]]). Wird von
 * NotenService::archiviereLehrjahrende() per extract() befuellt - EIN PDF
 * je archiviertem Lehrjahr, keine Wiederverwendung fuer die live
 * abgefragte Notenstand-Ansicht (die laeuft ueber die API/Vue, nicht ueber
 * dieses Template).
 */
$e = static fn (?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
$notenArtLabel = [
	'schriftlich' => 'Schriftlich',
	'muendlich' => 'Mündlich',
	'stehgreif' => 'Stegreifaufgabe',
];

?>
<style>
	html, body { margin: 0; padding: 0; }
	.notenschnitt { font-family: sans-serif; font-size: 9pt; padding: 10mm; }
	.notenschnitt h1 { font-size: 14pt; margin: 0 0 2mm; }
	.notenschnitt .zeitraum { color: #444; margin-bottom: 6mm; }
	.notenschnitt table.fach { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
	.notenschnitt table.fach th, .notenschnitt table.fach td { border: 0.3mm solid #666; padding: 1.5mm 2mm; }
	.notenschnitt table.fach th { background: #eee; font-size: 8pt; text-align: left; }
	.notenschnitt .fach-kopf { font-size: 11pt; font-weight: bold; margin: 0 0 1mm; }
	.notenschnitt .schnitt { font-size: 9pt; font-weight: bold; margin-bottom: 1mm; }
	.notenschnitt .keine-noten { color: #666; font-style: italic; margin-bottom: 4mm; }
</style>

<div class="notenschnitt">
	<h1>Notenschnitt - <?= $e($azubiName) ?></h1>
	<div class="zeitraum">
		Lehrjahr <?= $e((string)$lehrjahr) ?>, Zeitraum <?= $e($zeitraumVonFormatiert) ?> bis <?= $e($zeitraumBisFormatiert) ?>
	</div>

	<?php foreach ($faecher as $fach): ?>
		<div class="fach-kopf"><?= $e($fach['fachName']) ?></div>
		<div class="schnitt">
			Notenschnitt: <?= $fach['schnitt'] !== null ? $e(number_format($fach['schnitt'], 2, ',', '.')) : 'keine Note' ?>
		</div>
		<?php if (count($fach['noten']) === 0): ?>
			<p class="keine-noten">Keine Noten in diesem Zeitraum erfasst.</p>
		<?php else: ?>
			<table class="fach">
				<tr>
					<th>Datum</th>
					<th>Art</th>
					<th>Note</th>
					<th>Gewichtung</th>
				</tr>
				<?php foreach ($fach['noten'] as $n): ?>
					<tr>
						<td><?= $e((new DateTimeImmutable($n['datum']))->format('d.m.Y')) ?></td>
						<td><?= $e($notenArtLabel[$n['art']] ?? $n['art']) ?></td>
						<td><?= $e((string)$n['note']) ?></td>
						<td><?= $e(number_format($n['gewicht'] * 100, 0)) ?>%</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
