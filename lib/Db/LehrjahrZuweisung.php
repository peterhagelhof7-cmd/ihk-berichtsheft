<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getAzubiId()
 * @method void setAzubiId(int $value)
 * @method string getGueltigAb()
 * @method void setGueltigAb(string $value)
 * @method int getLehrjahr()
 * @method void setLehrjahr(int $value)
 * @method string getFestgelegtVonUserId()
 * @method void setFestgelegtVonUserId(string $value)
 * @method int getFestgelegtAm()
 * @method void setFestgelegtAm(int $value)
 */
class LehrjahrZuweisung extends Entity {
	protected $azubiId;
	protected $gueltigAb;
	protected $lehrjahr;
	protected $festgelegtVonUserId;
	protected $festgelegtAm;

	public function __construct() {
		$this->addType('azubiId', 'integer');
		$this->addType('lehrjahr', 'integer');
		$this->addType('festgelegtAm', 'integer');
	}
}
