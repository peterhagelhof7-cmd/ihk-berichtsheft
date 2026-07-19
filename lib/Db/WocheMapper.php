<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Woche>
 */
class WocheMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bh_woche', Woche::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): Woche {
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
	public function findByAzubiAndWocheVon(int $azubiId, string $wocheVon): Woche {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('woche_von', $qb->createNamedParameter($wocheVon, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByAzubiAndNachweisNr(int $azubiId, int $nachweisNr): Woche {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('nachweis_nr', $qb->createNamedParameter($nachweisNr, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * @return Woche[] sortiert nach nachweis_nr (die gedruckte "Ausbildungs-
	 * nachweis Nr." auf jeder PDF-Seite) - NICHT nach woche_von. Beides
	 * faellt im Normalfall zusammen, kann aber auseinanderlaufen (z.B. wenn
	 * rueckwirkend eine fruehere Kalenderwoche nachgetragen wird und dabei
	 * eine hoehere nachweis_nr als eine bereits bestehende spaetere Woche
	 * bekommt) - die gedruckte Seitenreihenfolge darf der gedruckten
	 * Nummer dann nie widersprechen.
	 */
	public function findByExportId(int $exportId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('export_id', $qb->createNamedParameter($exportId, IQueryBuilder::PARAM_INT)))
			->orderBy('nachweis_nr', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return Woche[] alle akzeptierten Wochen eines Azubis, sortiert nach nachweis_nr (Gesamtexport, s. findByExportId) */
	public function findAkzeptiertByAzubi(int $azubiId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Woche::STATUS_AKZEPTIERT, IQueryBuilder::PARAM_STR)))
			->orderBy('nachweis_nr', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return Woche[] alle Wochen mit Status 'eingereicht', neueste zuerst (Pruefung.vue) */
	public function findEingereicht(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('status', $qb->createNamedParameter(Woche::STATUS_EINGEREICHT, IQueryBuilder::PARAM_STR)))
			->orderBy('eingereicht_am', 'DESC');
		return $this->findEntities($qb);
	}

	public function letzteNachweisNr(int $azubiId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->max('nachweis_nr'), 'max_nr')
			->from($this->getTableName())
			->where($qb->expr()->eq('azubi_id', $qb->createNamedParameter($azubiId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return $max === false || $max === null ? 0 : (int)$max;
	}

	/**
	 * Atomarer Statuswechsel gegen gleichzeitiges Akzeptieren/Zurueckweisen
	 * durch zwei Ausbilder (Plan Abschnitt 3, Race-Condition-Schutz).
	 * Gibt true zurueck, wenn genau diese Zeile den Wechsel vorgenommen hat.
	 * @throws DbException
	 */
	public function statusWechselNurWennEingereicht(int $wocheId, string $neuerStatus): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('status', $qb->createNamedParameter($neuerStatus, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($wocheId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Woche::STATUS_EINGEREICHT, IQueryBuilder::PARAM_STR)));
		return $qb->executeStatement() === 1;
	}
}
