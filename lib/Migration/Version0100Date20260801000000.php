<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Ausbildungsberuf-Katalog als eigene Tabelle (bh_beruf) - loest die frueher
 * fest im Code (AusbildungsberufHelper) hinterlegte Liste ab, damit Ausbilder
 * weitere Berufe selbst anlegen koennen (andere Branchen). Die 7 bisher fest
 * verdrahteten Berufe werden mit ihren bestehenden Kuerzeln geseedet, damit
 * bereits angelegte Azubis (bh_azubi.ausbildungsberuf) weiter aufgeloest
 * werden. Idempotent: vorhandene Kuerzel werden nicht doppelt angelegt.
 */
class Version0100Date20260801000000 extends SimpleMigrationStep {
	/** berufKey => [Bezeichnung, Fachrichtung|null] */
	private const BERUFE = [
		'fiae' => ['Fachinformatiker/-in', 'Anwendungsentwicklung'],
		'fisi' => ['Fachinformatiker/-in', 'Systemintegration'],
		'fidp' => ['Fachinformatiker/-in', 'Daten- und Prozessanalyse'],
		'fidv' => ['Fachinformatiker/-in', 'Digitale Vernetzung'],
		'kfitsm' => ['Kaufmann/-frau für IT-System-Management', null],
		'kfdm' => ['Kaufmann/-frau für Digitalisierungsmanagement', null],
		'itse' => ['IT-System-Elektroniker/-in', null],
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('bh_beruf')) {
			$table = $schema->createTable('bh_beruf');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			// Stabiler Kurz-Schluessel, wird auf dem Azubi gespeichert
			// (bh_azubi.ausbildungsberuf, ebenfalls length 16).
			$table->addColumn('beruf_key', 'string', [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('bezeichnung', 'string', [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('fachrichtung', 'string', [
				'notnull' => false,
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
			$table->addUniqueIndex(['beruf_key'], 'bh_beruf_key_uniq');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$now = time();
		foreach (self::BERUFE as $key => [$bezeichnung, $fachrichtung]) {
			$q = $this->db->getQueryBuilder();
			$q->select('id')->from('bh_beruf')
				->where($q->expr()->eq('beruf_key', $q->createNamedParameter($key)));
			if ($q->executeQuery()->fetchOne() !== false) {
				continue; // schon vorhanden
			}
			$ins = $this->db->getQueryBuilder();
			$ins->insert('bh_beruf')->values([
				'beruf_key' => $ins->createNamedParameter($key),
				'bezeichnung' => $ins->createNamedParameter($bezeichnung),
				'fachrichtung' => $fachrichtung === null
					? $ins->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
					: $ins->createNamedParameter($fachrichtung),
				'created_at' => $ins->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				'updated_at' => $ins->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			]);
			$ins->executeStatement();
			$output->info("Beruf '$key' ($bezeichnung) angelegt.");
		}
	}
}
