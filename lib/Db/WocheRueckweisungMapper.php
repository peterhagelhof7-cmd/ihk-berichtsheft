<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<WocheRueckweisung>
 */
class WocheRueckweisungMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_woche_rueckweisung', WocheRueckweisung::class);
	}

	/** Historie aller Zurueckweisungsrunden einer Woche, aelteste zuerst. @return WocheRueckweisung[] */
	public function findByWocheId(int $wocheId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('woche_id', $qb->createNamedParameter($wocheId, IQueryBuilder::PARAM_INT)))
			->orderBy('zurueckgewiesen_am', 'ASC');
		return $this->findEntities($qb);
	}
}
