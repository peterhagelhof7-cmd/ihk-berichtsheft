<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<LehrjahrZuweisung>
 */
class LehrjahrZuweisungMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_lehrjahr_zuweisung', LehrjahrZuweisung::class);
	}

	/**
	 * Die zu einem Stichtag gueltige Zuweisung: die Zeile mit dem groessten
	 * gueltig_ab <= $datum. NICHT berechnet, siehe Plan Abschnitt 2.
	 * @throws DoesNotExistException wenn noch keine Zuweisung existiert
	 * @throws MultipleObjectsReturnedException
	 */
	public function findAktuellFuerAzubi(int $azubiId, string $datum): LehrjahrZuweisung {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('gueltig_ab', $qb->createNamedParameter($datum, IQueryBuilder::PARAM_STR)))
			->orderBy('gueltig_ab', 'DESC')
			->setMaxResults(1);
		return $this->findEntity($qb);
	}

	/** Historie aller Zuweisungen eines Azubis, neueste zuerst. @return LehrjahrZuweisung[] */
	public function findByAzubiId(int $azubiId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->orderBy('gueltig_ab', 'DESC');
		return $this->findEntities($qb);
	}

	public function existsForAzubiAndGueltigAb(int $azubiId, string $gueltigAb): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('gueltig_ab', $qb->createNamedParameter($gueltigAb, IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		$row = $result->fetchOne();
		$result->closeCursor();
		return $row !== false;
	}
}
