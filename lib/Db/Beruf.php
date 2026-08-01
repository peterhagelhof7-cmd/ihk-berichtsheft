<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * Ausbildungsberuf-Katalog (Ausbilder-pflegbar). Der `berufKey` ist der
 * kurze, stabile Bezeichner, der auf dem Azubi gespeichert wird
 * (bh_azubi.ausbildungsberuf) - abwaertskompatibel zu den frueher fest
 * verdrahteten Kuerzeln (fiae, fisi, itse ...). `fachrichtung` ist optional
 * (leer/"—" bei Berufen ohne Fachrichtung) und wird getrennt aufs Deckblatt
 * gedruckt, siehe DeckblattService/AusbildungsberufHelper.
 *
 * @method string getBerufKey()
 * @method void setBerufKey(string $value)
 * @method string getBezeichnung()
 * @method void setBezeichnung(string $value)
 * @method string|null getFachrichtung()
 * @method void setFachrichtung(?string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Beruf extends Entity {
	protected $berufKey;
	protected $bezeichnung;
	protected $fachrichtung;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
}
