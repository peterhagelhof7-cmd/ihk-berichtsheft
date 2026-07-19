<?php

declare(strict_types=1);

namespace OCA\Berichtsheft\Service;

use OCA\Berichtsheft\Db\Azubi;
use OCA\Berichtsheft\Db\Woche;
use OCP\IUserManager;
use OCP\L10N\IFactory as IL10NFactory;
use OCP\Mail\IMailer;

/**
 * Expliziter E-Mail-Versand ueber IMailer (Plan Abschnitt 3) - bewusst
 * NICHT ueber die abschaltbare persoenliche E-Mail-Benachrichtigungs-
 * Einstellung des Notifications-Kernmoduls, da die Anforderung
 * "Nextcloud UND E-Mail" sonst nicht garantiert waere. Jede Methode
 * schlaegt best-effort fehl (loggt, wirft aber nicht) - eine nicht
 * zustellbare Mail soll den eigentlichen Vorgang (Einreichen/Akzeptieren/
 * Zurueckweisen) nicht blockieren.
 */
class MailService {
	public function __construct(
		private IMailer $mailer,
		private IUserManager $userManager,
		private IL10NFactory $l10nFactory,
	) {
	}

	private function l() {
		return $this->l10nFactory->get('berichtsheft');
	}

	private function sendeAn(string $empfaengerUserId, string $betreff, string $heading, string $bodyText): void {
		$user = $this->userManager->get($empfaengerUserId);
		$email = $user?->getEMailAddress();
		if ($email === null) {
			return;
		}

		$template = $this->mailer->createEMailTemplate('berichtsheft.notification', []);
		$template->setSubject($betreff);
		$template->addHeader();
		$template->addHeading($heading);
		$template->addBodyText($bodyText);
		$template->addFooter();

		$message = $this->mailer->createMessage();
		$message->setTo([$email => $user->getDisplayName()]);
		$message->setSubject($betreff);
		$message->useTemplate($template);

		try {
			$this->mailer->send($message);
		} catch (\Exception) {
			// Best-effort - Versand-Fehler duerfen den Hauptvorgang nicht abbrechen.
		}
	}

	private function azubiName(Azubi $azubi): string {
		return trim(($azubi->getVorname() ?? '') . ' ' . ($azubi->getNachname() ?? '')) ?: $azubi->getUserId();
	}

	public function sendeWocheEingereicht(string $empfaengerUserId, Azubi $azubi, Woche $woche): void {
		$l = $this->l();
		$this->sendeAn(
			$empfaengerUserId,
			(string)$l->t('Woche Nr. %s wartet auf Prüfung', [$woche->getNachweisNr()]),
			(string)$l->t('Berichtsheft: Neue Einreichung'),
			(string)$l->t('%1$s hat Woche Nr. %2$s (%3$s bis %4$s) zur Prüfung eingereicht.', [
				$this->azubiName($azubi), $woche->getNachweisNr(), $woche->getWocheVon(), $woche->getWocheBis(),
			]),
		);
	}

	public function sendeWocheAkzeptiert(string $azubiUserId, Woche $woche): void {
		$l = $this->l();
		$this->sendeAn(
			$azubiUserId,
			(string)$l->t('Woche Nr. %s wurde akzeptiert', [$woche->getNachweisNr()]),
			(string)$l->t('Berichtsheft: Woche akzeptiert'),
			(string)$l->t('Deine Woche Nr. %1$s (%2$s bis %3$s) wurde von %4$s akzeptiert.', [
				$woche->getNachweisNr(), $woche->getWocheVon(), $woche->getWocheBis(), $woche->getAkzeptiertVonName(),
			]),
		);
	}

	/**
	 * Der Ausbilder-Kommentar ist zwingend Teil dieser E-Mail (Plan
	 * Abschnitt 3: "der Ausbilder-Kommentar ist Teil dieser E-Mail").
	 */
	public function sendeWocheZurueckgewiesen(string $azubiUserId, Woche $woche, string $kommentar): void {
		$l = $this->l();
		$this->sendeAn(
			$azubiUserId,
			(string)$l->t('Woche Nr. %s wurde zurückgewiesen', [$woche->getNachweisNr()]),
			(string)$l->t('Berichtsheft: Woche zurückgewiesen'),
			(string)$l->t("Deine Woche Nr. %1\$s (%2\$s bis %3\$s) wurde zurückgewiesen.\n\nKommentar:\n%4\$s\n\nBitte korrigiere die Einträge und reiche die Woche erneut ein.", [
				$woche->getNachweisNr(), $woche->getWocheVon(), $woche->getWocheBis(), $kommentar,
			]),
		);
	}

	public function sendeWochenerinnerung(string $azubiUserId): void {
		$l = $this->l();
		$this->sendeAn(
			$azubiUserId,
			(string)$l->t('Bitte Berichtsheft eintragen'),
			(string)$l->t('Berichtsheft: wöchentliche Erinnerung'),
			(string)$l->t('Bitte trage deine Einträge der vergangenen Woche im Berichtsheft nach und reiche die Woche ein.'),
		);
	}

	/** @param array<array{azubiName:string,wocheVon:string,status:string}> $zeilen */
	public function sendeAusbilderDigest(string $ausbilderUserId, array $zeilen): void {
		$l = $this->l();
		$text = '';
		foreach ($zeilen as $zeile) {
			$text .= sprintf("- %s (Woche %s): %s\n", $zeile['azubiName'], $zeile['wocheVon'], $zeile['status']);
		}
		$this->sendeAn(
			$ausbilderUserId,
			(string)$l->t('Berichtsheft-Wochenübersicht'),
			(string)$l->t('Berichtsheft: aktueller Stand aller Azubis'),
			$text !== '' ? $text : (string)$l->t('Aktuell liegen keine offenen Wochen vor.'),
		);
	}

	public function sendeLehrjahrAbfrage(string $ausbilderUserId, string $azubiName, string $stichtag): void {
		$l = $this->l();
		$this->sendeAn(
			$ausbilderUserId,
			(string)$l->t('Bitte Lehrjahr ab %s festlegen', [$stichtag]),
			(string)$l->t('Berichtsheft: Lehrjahr-Zuweisung erforderlich'),
			(string)$l->t('Bitte lege für %1$s das ab %2$s gültige Lehrjahr fest (Berichtsheft-Verwaltung).', [$azubiName, $stichtag]),
		);
	}
}
