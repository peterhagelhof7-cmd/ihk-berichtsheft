<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Notification;

use OCA\Berichtsheft\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Rendert die In-App-Benachrichtigungen (Glocke). Reine Darstellung - das
 * tatsaechliche Erzeugen/Versenden passiert an der jeweiligen Aufrufstelle
 * (EintragService, PruefungController, die Background-Jobs) ueber
 * IManager::notify(). Subjects siehe Plan Abschnitt 3.
 */
class Notifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Berichtsheft');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException('App stimmt nicht.');
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$parameters = $notification->getSubjectParameters();

		$subject = '';
		switch ($notification->getSubject()) {
			case 'woche-eingereicht':
				$subject = $l->t('%1$s hat Woche Nr. %2$s zur Prüfung eingereicht', [
					$parameters['azubiName'] ?? '?',
					$parameters['nachweisNr'] ?? '?',
				]);
				break;
			case 'woche-akzeptiert':
				$subject = $l->t('Woche Nr. %s wurde akzeptiert', [$parameters['nachweisNr'] ?? '?']);
				break;
			case 'woche-zurueckgewiesen':
				$subject = $l->t('Woche Nr. %1$s wurde zurückgewiesen: %2$s', [
					$parameters['nachweisNr'] ?? '?',
					$parameters['kommentar'] ?? '',
				]);
				break;
			case 'wochenerinnerung':
				$subject = $l->t('Bitte die vergangene Woche im Berichtsheft eintragen');
				break;
			case 'ausbilder-digest':
				$subject = $l->t('Berichtsheft-Wochenübersicht');
				break;
			case 'lehrjahr-abfrage':
				$subject = $l->t('Bitte Lehrjahr ab %1$s für %2$s festlegen', [
					$parameters['stichtag'] ?? '?',
					$parameters['azubiName'] ?? '?',
				]);
				break;
			case 'export-erzeugt':
				$subject = $l->t('4-Wochen-Export Nr. %s wurde erzeugt', [$parameters['exportNr'] ?? '?']);
				break;
			default:
				throw new UnknownNotificationException('Unbekanntes Subject: ' . $notification->getSubject());
		}

		// NC 34: setParsedSubject()/setIcon() werfen bei leerem/ungueltigem Wert eine
		// OCP\Notification\InvalidValueException (= \InvalidArgumentException). Diese ist
		// seit NC 34 deprecated und flutet sonst das Log ("...prepare() threw
		// \InvalidArgumentException which is deprecated..."). Vorgabe von NC 34: leeres
		// Subject vermeiden und jede verbleibende \InvalidArgumentException hier selbst
		// behandeln, statt sie entweichen zu lassen.
		if ($subject === '') {
			$subject = $l->t('Berichtsheft-Benachrichtigung');
		}
		try {
			$notification->setParsedSubject($subject);
			$notification->setIcon($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg'));
		} catch (\InvalidArgumentException $e) {
			// Unbrauchbare Notification ueberspringen (NC faengt UnknownNotificationException
			// still ab) statt das Log mit Deprecation-Warnungen zu fluten.
			throw new UnknownNotificationException($e->getMessage());
		}

		return $notification;
	}
}
