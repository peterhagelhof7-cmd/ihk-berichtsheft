<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Fach extends Entity {
	protected $name;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
}
