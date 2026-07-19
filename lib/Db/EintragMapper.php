<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Eintrag>
 */
class EintragMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_eintrag', Eintrag::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByAzubiAndDatum(int $azubiId, string $datum): Eintrag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('datum', $qb->createNamedParameter($datum, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * Alle Tageseintraege einer Woche (Mo-So), aufsteigend nach Datum sortiert.
	 * @return Eintrag[]
	 */
	public function findByAzubiAndDateRange(int $azubiId, string $von, string $bis): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('datum', $qb->createNamedParameter($von, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->lte('datum', $qb->createNamedParameter($bis, IQueryBuilder::PARAM_STR)))
			->orderBy('datum', 'ASC');
		return $this->findEntities($qb);
	}
}
