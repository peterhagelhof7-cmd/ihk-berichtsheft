<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Export>
 */
class ExportMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_export', Export::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByAzubiAndExportNr(int $azubiId, int $exportNr): Export {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('export_nr', $qb->createNamedParameter($exportNr, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Export[] alle noch nicht exportierten Buendel (fuer ExportGeneratorJob) */
	public function findWartendAufWochen(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Export::STATUS_WARTET_AUF_WOCHEN, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}

	public function letzteExportNr(int $azubiId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->max('export_nr'), 'max_nr')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === false || $max === null ? 0 : (int)$max;
	}
}
