<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Entfernt den UNIQUE-Index (azubi_id, nachweis_nr) auf bh_woche.
 *
 * nachweis_nr ist KEINE gueltige eindeutige Identitaet einer Woche: Wochen VOR
 * dem Ausbildungsstart haben keine echte Nachweis-Nr und teilen sich dieselbe
 * Nummer (0). Mehrere solche Wochen kollidierten dadurch auf dem UNIQUE-Index
 * -> HTTP 500 beim Oeffnen (Duplicate-Key bzw. DoesNotExist nach Re-Fetch).
 *
 * Die echte Identitaet einer Woche ist (azubi_id, woche_von) -- dieser
 * UNIQUE-Index (bh_woche_azubi_von_uniq) bleibt bestehen und verhindert weiter
 * doppelte Wochen. nachweis_nr bleibt als (nicht eindeutige) Anzeige-/Sortier-
 * und Export-Nummer erhalten (fuer echte Wochen ab Startwoche ist sie ohnehin
 * eindeutig). Defensiv: nur droppen, wenn vorhanden.
 */
class Version0100Date20260820000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bh_woche')) {
			return null;
		}
		$table = $schema->getTable('bh_woche');
		if ($table->hasIndex('bh_woche_azubi_nr_uniq')) {
			$table->dropIndex('bh_woche_azubi_nr_uniq');
			return $schema;
		}

		return null;
	}
}
