<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getEintragId()
 * @method void setEintragId(int $value)
 * @method int getPosition()
 * @method void setPosition(int $value)
 * @method int getFachId()
 * @method void setFachId(int $value)
 * @method float getStunden()
 * @method void setStunden(float $value)
 * @method string|null getInhalt()
 * @method void setInhalt(?string $value)
 */
class FachEintrag extends Entity {
	protected $eintragId;
	protected $position;
	protected $fachId;
	protected $stunden;
	protected $inhalt;

	public function __construct() {
		$this->addType('eintragId', 'integer');
		$this->addType('position', 'integer');
		$this->addType('fachId', 'integer');
		$this->addType('stunden', 'float');
	}
}
