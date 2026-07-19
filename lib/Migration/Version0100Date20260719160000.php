<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Optionaler Unterrichtsinhalt je Fach-Zeile an Berufsschultagen -
 * zusaetzlich zu Fach+Stunden, auf ausdruecklichen Nutzerwunsch (Fach+
 * Stunden allein war laut Plan Abschnitt 1 bewusst ausreichend, aber in
 * der Praxis wollte der Ausbilder trotzdem einen kurzen Inhaltsvermerk
 * je Fach im PDF sehen koennen).
 */
class Version0100Date20260719160000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('bh_fach_eintrag');
		if (!$table->hasColumn('inhalt')) {
			$table->addColumn('inhalt', 'text', [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
