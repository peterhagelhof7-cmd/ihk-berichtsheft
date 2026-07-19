<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FachEintrag>
 */
class FachEintragMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_fach_eintrag', FachEintrag::class);
	}

	/** @return FachEintrag[] */
	public function findByEintragId(int $eintragId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('eintrag_id', $qb->createNamedParameter($eintragId, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Loescht alle Fach-Zeilen eines Tages vor dem Neuschreiben beim
	 * Speichern eines Berufsschultags (einfacher als Diffing).
	 */
	public function deleteByEintragId(int $eintragId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('eintrag_id', $qb->createNamedParameter($eintragId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
