<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FachLehrjahr>
 */
class FachLehrjahrMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_fach_lehrjahr', FachLehrjahr::class);
	}

	/** @return FachLehrjahr[] */
	public function findByFachId(int $fachId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('fach_id', $qb->createNamedParameter($fachId, IQueryBuilder::PARAM_INT)));
		return $this->findEntities($qb);
	}

	/**
	 * Alle fach_id's, die im angegebenen Lehrjahr gelten - Basis fuer die
	 * Fach-Auswahl in der Berufsschul-Eingabe (EintragService).
	 * @return int[]
	 */
	public function findFachIdsByLehrjahr(int $lehrjahr): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('fach_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('lehrjahr', $qb->createNamedParameter($lehrjahr, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$ids = array_map('intval', $result->fetchFirstColumn());
		$result->closeCursor();
		return $ids;
	}

	public function deleteByFachId(int $fachId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('fach_id', $qb->createNamedParameter($fachId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
