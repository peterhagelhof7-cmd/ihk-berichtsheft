<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getAzubiId()
 * @method void setAzubiId(int $value)
 * @method int getNachweisNr()
 * @method void setNachweisNr(int $value)
 * @method string getWocheVon()
 * @method void setWocheVon(string $value)
 * @method string getWocheBis()
 * @method void setWocheBis(string $value)
 * @method string|null getBemerkungen()
 * @method void setBemerkungen(?string $value)
 * @method string getStatus()
 * @method void setStatus(string $value)
 * @method string|null getEingereichtVonUserId()
 * @method void setEingereichtVonUserId(?string $value)
 * @method string|null getEingereichtVonName()
 * @method void setEingereichtVonName(?string $value)
 * @method int|null getEingereichtAm()
 * @method void setEingereichtAm(?int $value)
 * @method string|null getAkzeptiertVonUserId()
 * @method void setAkzeptiertVonUserId(?string $value)
 * @method string|null getAkzeptiertVonName()
 * @method void setAkzeptiertVonName(?string $value)
 * @method int|null getAkzeptiertAm()
 * @method void setAkzeptiertAm(?int $value)
 * @method int|null getExportId()
 * @method void setExportId(?int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Woche extends Entity {
	public const STATUS_OFFEN = 'offen';
	public const STATUS_EINGEREICHT = 'eingereicht';
	public const STATUS_AKZEPTIERT = 'akzeptiert';
	public const STATUS_ZURUECKGEWIESEN = 'zurueckgewiesen';

	protected $azubiId;
	protected $nachweisNr;
	protected $wocheVon;
	protected $wocheBis;
	protected $bemerkungen;
	protected $status;
	protected $eingereichtVonUserId;
	protected $eingereichtVonName;
	protected $eingereichtAm;
	protected $akzeptiertVonUserId;
	protected $akzeptiertVonName;
	protected $akzeptiertAm;
	protected $exportId;
	protected $createdAt;

	public function __construct() {
		$this->addType('azubiId', 'integer');
		$this->addType('nachweisNr', 'integer');
		$this->addType('eingereichtAm', 'integer');
		$this->addType('akzeptiertAm', 'integer');
		$this->addType('exportId', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
