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
 * Standard-Faecher als Vorlage seeden (die auf dem Testsystem bereits manuell
 * angelegten Berufsschulfaecher werden damit Teil der Auslieferung). Idempotent:
 * ein Fach mit gleichem Namen wird nicht doppelt angelegt - auf Instanzen mit
 * bereits vorhandenen Faechern passiert also nichts, auf frischen Instanzen
 * werden sie inkl. Lehrjahr-Zuordnung erstellt.
 */
class Version0100Date20260728120000 extends SimpleMigrationStep {
	/** Fachname => Lehrjahre, in denen es zur Auswahl steht. */
	private const FAECHER = [
		'IT-TEC' => [1, 2, 3, 4],
		'BGWP' => [1, 2, 3, 4],
		'PuG' => [1, 2, 3, 4],
		'FU-IT' => [1, 2, 3, 4],
		'AEuP' => [1, 2, 3, 4],
		'Deutsch' => [1, 2, 3, 4],
		'English' => [1, 2, 3, 4],
		'IT-P' => [2, 3, 4],
		'FÖ' => [2, 3, 4],
		'Religion' => [1, 2, 3, 4],
		'Sport' => [1, 2, 3, 4],
		'IT-SYS' => [1, 2, 3, 4],
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$now = time();
		foreach (self::FAECHER as $name => $lehrjahre) {
			// Schon vorhanden? (idempotent, nicht doppeln)
			$q = $this->db->getQueryBuilder();
			$q->select('id')->from('bh_fach')
				->where($q->expr()->eq('name', $q->createNamedParameter($name)));
			$fachId = $q->executeQuery()->fetchOne();

			if ($fachId === false) {
				$ins = $this->db->getQueryBuilder();
				$ins->insert('bh_fach')->values([
					'name' => $ins->createNamedParameter($name),
					'created_at' => $ins->createNamedParameter($now, IQueryBuilder::PARAM_INT),
					'updated_at' => $ins->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				]);
				$ins->executeStatement();

				$q2 = $this->db->getQueryBuilder();
				$q2->select('id')->from('bh_fach')
					->where($q2->expr()->eq('name', $q2->createNamedParameter($name)));
				$fachId = (int)$q2->executeQuery()->fetchOne();

				foreach ($lehrjahre as $lj) {
					$ljIns = $this->db->getQueryBuilder();
					$ljIns->insert('bh_fach_lehrjahr')->values([
						'fach_id' => $ljIns->createNamedParameter($fachId, IQueryBuilder::PARAM_INT),
						'lehrjahr' => $ljIns->createNamedParameter($lj, IQueryBuilder::PARAM_INT),
					]);
					$ljIns->executeStatement();
				}
				$output->info("Fach '$name' angelegt (Lehrjahre " . implode(',', $lehrjahre) . ').');
			}
		}
	}
}
