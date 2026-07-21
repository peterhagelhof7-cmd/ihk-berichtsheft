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
 * @method string|null getNoteArt()
 * @method void setNoteArt(?string $value)
 * @method int|null getNote()
 * @method void setNote(?int $value)
 */
class FachEintrag extends Entity {
	public const NOTE_ART_SCHRIFTLICH = 'schriftlich';
	public const NOTE_ART_MUENDLICH = 'muendlich';
	public const NOTE_ART_STEHGREIF = 'stehgreif';

	/** Gewichtung je Notenart fuer den Notenschnitt (Plan: muendlich/stehgreif zaehlen nur halb). */
	public const NOTE_GEWICHT = [
		self::NOTE_ART_SCHRIFTLICH => 1.0,
		self::NOTE_ART_MUENDLICH => 0.5,
		self::NOTE_ART_STEHGREIF => 0.5,
	];

	protected $eintragId;
	protected $position;
	protected $fachId;
	protected $stunden;
	protected $inhalt;
	protected $noteArt;
	protected $note;

	public function __construct() {
		$this->addType('eintragId', 'integer');
		$this->addType('position', 'integer');
		$this->addType('fachId', 'integer');
		$this->addType('stunden', 'float');
		$this->addType('note', 'integer');
	}
}
