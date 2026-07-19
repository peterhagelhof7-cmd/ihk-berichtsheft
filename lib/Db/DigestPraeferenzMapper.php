<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<DigestPraeferenz>
 */
class DigestPraeferenzMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_digest_praeferenz', DigestPraeferenz::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByAusbilderUserId(string $ausbilderUserId): DigestPraeferenz {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('ausbilder_user_id', $qb->createNamedParameter($ausbilderUserId, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}
}
