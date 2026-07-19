<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getAusbilderUserId()
 * @method void setAusbilderUserId(string $value)
 * @method int|null getWochentag()
 * @method void setWochentag(?int $value)
 * @method int|null getUhrzeitStunde()
 * @method void setUhrzeitStunde(?int $value)
 * @method string|null getLastDigestSentOn()
 * @method void setLastDigestSentOn(?string $value)
 */
class DigestPraeferenz extends Entity {
	/** ISO-8601: Montag = 1 ... Sonntag = 7. NULL = Default (Montag). */
	public const DEFAULT_WOCHENTAG = 1;
	/** NULL = Default (10 Uhr). */
	public const DEFAULT_UHRZEIT_STUNDE = 10;

	protected $ausbilderUserId;
	protected $wochentag;
	protected $uhrzeitStunde;
	protected $lastDigestSentOn;

	public function __construct() {
		$this->addType('wochentag', 'integer');
		$this->addType('uhrzeitStunde', 'integer');
	}
}
