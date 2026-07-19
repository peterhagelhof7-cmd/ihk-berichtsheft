<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Azubi-Status (aktiv/beendet) - Ausbildung beenden statt loeschen: die
 * Azubi-Zeile, alle Wochen/PDFs und der Datei-Ordner bleiben unveraendert
 * erhalten, ein beendeter Azubi verschwindet nur aus der aktiven
 * Verwaltungsliste und ist jederzeit reaktivierbar.
 */
class Version0100Date20260719150000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('bh_azubi');
		if (!$table->hasColumn('status')) {
			$table->addColumn('status', 'string', [
				'notnull' => true,
				'length' => 16,
				'default' => 'aktiv',
			]);
		}

		return $schema;
	}
}
