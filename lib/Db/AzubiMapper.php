<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Azubi>
 */
class AzubiMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_azubi', Azubi::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Azubi {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByUserId(string $userId): Azubi {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	public function existsForUserId(string $userId): bool {
		try {
			$this->findByUserId($userId);
			return true;
		} catch (DoesNotExistException) {
			return false;
		}
	}

	/** @return Azubi[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/** Azubis, deren Ausbildung bereits begonnen hat (fuer Reminder-/Digest-Jobs). @return Azubi[] */
	public function findActiveOn(string $datum): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->lte('ausbildungsstart', $qb->createNamedParameter($datum, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}

	/** @return Azubi[] */
	public function findByVerantwortlicherAusbilder(string $ausbilderUserId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('verantwortlicher_ausbilder_user_id', $qb->createNamedParameter($ausbilderUserId, IQueryBuilder::PARAM_STR)));
		return $this->findEntities($qb);
	}
}
