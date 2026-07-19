<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initiales Datenbankschema, siehe Plan Abschnitt 2
 * (C:\Users\Admin\.claude\plans\merry-booping-swing.md).
 */
class Version0100Date20260719000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bh_azubi')) {
			$table = $schema->createTable('bh_azubi');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('ausbildungsberuf', 'string', [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('ausbildungsstart', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('verantwortlicher_ausbilder_user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('ausbildungsabteilung', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('ausbildungsjahr_start_wert', 'smallint', [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('vorname', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('nachname', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('last_reminder_sent_on', 'date', [
				'notnull' => false,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'bh_azubi_user_uniq');
			$table->addIndex(['verantwortlicher_ausbilder_user_id'], 'bh_azubi_verantw_idx');
		}

		if (!$schema->hasTable('bh_eintrag')) {
			$table = $schema->createTable('bh_eintrag');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('azubi_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('datum', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('tag_typ', 'string', [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('taetigkeit', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('stunden', 'decimal', [
				'notnull' => false,
				'precision' => 4,
				'scale' => 2,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['azubi_id', 'datum'], 'bh_eintrag_azubi_datum_uniq');
		}

		if (!$schema->hasTable('bh_fach_eintrag')) {
			$table = $schema->createTable('bh_fach_eintrag');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('eintrag_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('position', 'smallint', [
				'notnull' => true,
			]);
			$table->addColumn('fach_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('stunden', 'decimal', [
				'notnull' => true,
				'precision' => 4,
				'scale' => 2,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['eintrag_id'], 'bh_fach_eintrag_eintrag_idx');
			$table->addIndex(['fach_id'], 'bh_fach_eintrag_fach_idx');
		}

		if (!$schema->hasTable('bh_fach')) {
			$table = $schema->createTable('bh_fach');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('updated_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('bh_fach_lehrjahr')) {
			$table = $schema->createTable('bh_fach_lehrjahr');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('fach_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('lehrjahr', 'smallint', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['fach_id', 'lehrjahr'], 'bh_fach_lehrjahr_uniq');
		}

		if (!$schema->hasTable('bh_digest_praeferenz')) {
			$table = $schema->createTable('bh_digest_praeferenz');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('ausbilder_user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('wochentag', 'smallint', [
				'notnull' => false,
			]);
			$table->addColumn('uhrzeit_stunde', 'smallint', [
				'notnull' => false,
			]);
			$table->addColumn('last_digest_sent_on', 'date', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['ausbilder_user_id'], 'bh_digest_praef_user_uniq');
		}

		if (!$schema->hasTable('bh_lehrjahr_zuweisung')) {
			$table = $schema->createTable('bh_lehrjahr_zuweisung');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('azubi_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('gueltig_ab', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('lehrjahr', 'smallint', [
				'notnull' => true,
			]);
			$table->addColumn('festgelegt_von_user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('festgelegt_am', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['azubi_id', 'gueltig_ab'], 'bh_lehrjahr_zuw_uniq');
		}

		if (!$schema->hasTable('bh_woche')) {
			$table = $schema->createTable('bh_woche');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('azubi_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('nachweis_nr', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('woche_von', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('woche_bis', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('bemerkungen', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('status', 'string', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('eingereicht_von_user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('eingereicht_von_name', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('eingereicht_am', 'integer', [
				'notnull' => false,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('akzeptiert_von_user_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('akzeptiert_von_name', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('akzeptiert_am', 'integer', [
				'notnull' => false,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('export_id', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['azubi_id', 'woche_von'], 'bh_woche_azubi_von_uniq');
			$table->addUniqueIndex(['azubi_id', 'nachweis_nr'], 'bh_woche_azubi_nr_uniq');
			$table->addIndex(['export_id'], 'bh_woche_export_idx');
			$table->addIndex(['status'], 'bh_woche_status_idx');
		}

		if (!$schema->hasTable('bh_woche_rueckweisung')) {
			$table = $schema->createTable('bh_woche_rueckweisung');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('woche_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('ausbilder_user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('kommentar', 'text', [
				'notnull' => true,
			]);
			$table->addColumn('zurueckgewiesen_am', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['woche_id'], 'bh_woche_rueckw_woche_idx');
		}

		if (!$schema->hasTable('bh_export')) {
			$table = $schema->createTable('bh_export');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('azubi_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('export_nr', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('zeitraum_von', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('zeitraum_bis', 'date', [
				'notnull' => true,
			]);
			$table->addColumn('status', 'string', [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('file_id', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('file_path', 'string', [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->addColumn('generated_at', 'integer', [
				'notnull' => false,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('created_at', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['azubi_id', 'export_nr'], 'bh_export_azubi_nr_uniq');
			$table->addIndex(['status'], 'bh_export_status_idx');
		}

		return $schema;
	}
}
