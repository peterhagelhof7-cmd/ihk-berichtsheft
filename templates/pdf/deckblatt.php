<?php

declare(strict_types=1);

/**
 * Erwartete Variablen: $nachname, $vorname, $betriebsAdresse,
 * $ausbildungsberuf, $fachrichtung, $betriebsName, $verantwortlicherName,
 * $logoDataUri (string|null - vom Ausbilder in den Betriebseinstellungen
 * gewaehltes Logo als base64-Data-URI, s. LogoService; null = kein Logo
 * gesetzt oder nicht mehr ladbar, dann einfach ohne rendern).
 * Wird von DeckblattService per extract() befuellt und ueber dompdf
 * gerendert (Plan Abschnitt 1/4).
 */
$e = static fn (?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

?>
<style>
	.deckblatt { position: relative; font-family: sans-serif; padding: 30mm 20mm; }
	.deckblatt h1 { font-size: 20pt; text-align: center; margin-bottom: 20mm; }
	.deckblatt table { width: 100%; border-collapse: collapse; }
	.deckblatt td { padding: 4mm 2mm; vertical-align: top; font-size: 11pt; }
	.deckblatt td.label { width: 45%; font-weight: bold; }
	.deckblatt-logo { position: absolute; top: 10mm; right: 10mm; max-width: 40mm; max-height: 25mm; }
</style>

<div class="deckblatt">
	<?php if ($logoDataUri !== null): ?>
		<img class="deckblatt-logo" src="<?= $e($logoDataUri) ?>" alt="Logo">
	<?php endif; ?>
	<h1>BERICHTSHEFT</h1>
	<table>
		<tr>
			<td class="label">Name, Vorname:</td>
			<td><?= $e($nachname) ?>, <?= $e($vorname) ?></td>
		</tr>
		<tr>
			<td class="label">Adresse:</td>
			<td><?= $e($betriebsAdresse) ?></td>
		</tr>
		<tr>
			<td class="label">Ausbildungsberuf:</td>
			<td><?= $e($ausbildungsberuf) ?></td>
		</tr>
		<tr>
			<td class="label">Fachrichtung/Schwerpunkt:</td>
			<td><?= $e($fachrichtung) ?></td>
		</tr>
		<tr>
			<td class="label">Ausbildungsbetrieb:</td>
			<td><?= $e($betriebsName) ?></td>
		</tr>
		<tr>
			<td class="label">Verantwortliche/r Ausbilder/in:</td>
			<td><?= $e($verantwortlicherName) ?></td>
		</tr>
	</table>
</div>
