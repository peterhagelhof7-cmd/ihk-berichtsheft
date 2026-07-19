<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getAzubiId()
 * @method void setAzubiId(int $value)
 * @method string getDatum()
 * @method void setDatum(string $value)
 * @method string getTagTyp()
 * @method void setTagTyp(string $value)
 * @method string|null getTaetigkeit()
 * @method void setTaetigkeit(?string $value)
 * @method float|null getStunden()
 * @method void setStunden(?float $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Eintrag extends Entity {
	public const TAG_TYP_BETRIEB = 'betrieb';
	public const TAG_TYP_BERUFSSCHULE = 'berufsschule';
	public const TAG_TYP_FEIERTAG = 'feiertag';
	public const TAG_TYP_URLAUB = 'urlaub';
	public const TAG_TYP_KRANKHEIT = 'krankheit';

	protected $azubiId;
	protected $datum;
	protected $tagTyp;
	protected $taetigkeit;
	protected $stunden;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('azubiId', 'integer');
		$this->addType('stunden', 'float');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
}
