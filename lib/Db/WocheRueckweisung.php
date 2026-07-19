<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getWocheId()
 * @method void setWocheId(int $value)
 * @method string getAusbilderUserId()
 * @method void setAusbilderUserId(string $value)
 * @method string getKommentar()
 * @method void setKommentar(string $value)
 * @method int getZurueckgewiesenAm()
 * @method void setZurueckgewiesenAm(int $value)
 */
class WocheRueckweisung extends Entity {
	protected $wocheId;
	protected $ausbilderUserId;
	protected $kommentar;
	protected $zurueckgewiesenAm;

	public function __construct() {
		$this->addType('wocheId', 'integer');
		$this->addType('zurueckgewiesenAm', 'integer');
	}
}
