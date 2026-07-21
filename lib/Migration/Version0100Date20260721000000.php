<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Notenverwaltung: optionale Note je Fach-Zeile eines Berufsschultags.
 * note_art ist eine von Fach::NOTE_ART_* (schriftlich/muendlich/stehgreif)
 * oder NULL (keine Note an diesem Tag), note ist 1-6. Bewusst als
 * zusaetzliche Spalten auf bh_fach_eintrag statt einer eigenen Tabelle -
 * eine Note gehoert untrennbar zu genau einer Fach-Zeile eines Tages und
 * wird beim Neuschreiben des Tages (EintragService::speichereEintrag,
 * deleteByEintragId+insert) automatisch mitgeloescht/neu angelegt, ohne
 * eigene FK-Pflege.
 */
class Version0100Date20260721000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('bh_fach_eintrag');
		if (!$table->hasColumn('note_art')) {
			$table->addColumn('note_art', 'string', [
				'notnull' => false,
				'length' => 16,
			]);
		}
		if (!$table->hasColumn('note')) {
			$table->addColumn('note', 'smallint', [
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
