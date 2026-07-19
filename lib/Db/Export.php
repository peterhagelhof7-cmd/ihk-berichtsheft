<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getAzubiId()
 * @method void setAzubiId(int $value)
 * @method int getExportNr()
 * @method void setExportNr(int $value)
 * @method string getZeitraumVon()
 * @method void setZeitraumVon(string $value)
 * @method string getZeitraumBis()
 * @method void setZeitraumBis(string $value)
 * @method string getStatus()
 * @method void setStatus(string $value)
 * @method int|null getFileId()
 * @method void setFileId(?int $value)
 * @method string|null getFilePath()
 * @method void setFilePath(?string $value)
 * @method int|null getGeneratedAt()
 * @method void setGeneratedAt(?int $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 */
class Export extends Entity {
	public const STATUS_WARTET_AUF_WOCHEN = 'wartet_auf_wochen';
	public const STATUS_EXPORTIERT = 'exportiert';

	protected $azubiId;
	protected $exportNr;
	protected $zeitraumVon;
	protected $zeitraumBis;
	protected $status;
	protected $fileId;
	protected $filePath;
	protected $generatedAt;
	protected $createdAt;

	public function __construct() {
		$this->addType('azubiId', 'integer');
		$this->addType('exportNr', 'integer');
		$this->addType('fileId', 'integer');
		$this->addType('generatedAt', 'integer');
		$this->addType('createdAt', 'integer');
	}
}
