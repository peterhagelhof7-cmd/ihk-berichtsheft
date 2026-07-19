<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getAusbildungsberuf()
 * @method void setAusbildungsberuf(string $value)
 * @method string getAusbildungsstart()
 * @method void setAusbildungsstart(string $value)
 * @method string getVerantwortlicherAusbilderUserId()
 * @method void setVerantwortlicherAusbilderUserId(string $value)
 * @method string|null getAusbildungsabteilung()
 * @method void setAusbildungsabteilung(?string $value)
 * @method int getAusbildungsjahrStartWert()
 * @method void setAusbildungsjahrStartWert(int $value)
 * @method string|null getVorname()
 * @method void setVorname(?string $value)
 * @method string|null getNachname()
 * @method void setNachname(?string $value)
 * @method string|null getLastReminderSentOn()
 * @method void setLastReminderSentOn(?string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class Azubi extends Entity {
	protected $userId;
	protected $ausbildungsberuf;
	protected $ausbildungsstart;
	protected $verantwortlicherAusbilderUserId;
	protected $ausbildungsabteilung;
	protected $ausbildungsjahrStartWert;
	protected $vorname;
	protected $nachname;
	protected $lastReminderSentOn;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('ausbildungsjahrStartWert', 'integer');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}
}
