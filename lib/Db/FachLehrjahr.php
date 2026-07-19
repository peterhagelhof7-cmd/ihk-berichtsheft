<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFachId()
 * @method void setFachId(int $value)
 * @method int getLehrjahr()
 * @method void setLehrjahr(int $value)
 */
class FachLehrjahr extends Entity {
	protected $fachId;
	protected $lehrjahr;

	public function __construct() {
		$this->addType('fachId', 'integer');
		$this->addType('lehrjahr', 'integer');
	}
}
