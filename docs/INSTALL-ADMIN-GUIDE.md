# Installations- und Administrationsleitfaden — Berichtsheft

Zielgruppe: der Systemverwalter, der die App auf einer echten
Nextcloud-Instanz einrichtet und danach betreut.

**Stand dieses Dokuments:** Version **0.11.2**. Die App wird auf einer
Nextcloud-Testinstanz (Server, unter `test-berichtsheft.itp-solutions.de`)
sowie lokal (WSL2, Nextcloud 34.0.1) betrieben und geprüft: kompletter
Azubi/Ausbilder-Workflow per echter Browser-Bedienung, alle Hintergrundjobs
per `occ`, E-Mail-Versand über einen echten SMTP-Account (inkl. des
Instanz-Links in der Benachrichtigung), PDF-Ausgabe visuell per Rendering
geprüft, dazu eine automatisierte PHPUnit-Testsuite. **Noch nicht geprüft:**
Verhalten auf jeder weiteren konkreten Zielinstanz (anderer Server, andere
PHP-/DB-Version, echte Nutzer). Vor dem produktiven Einsatz Abschnitt 8
(Funktion prüfen) einmal vollständig durchgehen.

Seit 0.9.0 hinzugekommen (Auswahl): Ausbilder-pflegbarer Katalog der
Ausbildungsberufe und Fächer (auch für andere Branchen nutzbar),
Notenverwaltung mit gewichtetem Schnitt, IHK-Gesamtnachweis-Export,
Azubi-Status (beenden/reaktivieren), Unterrichtsinhalt je Fach,
E-Mail-Benachrichtigungen mit direktem Link zur Instanz.

Repository: `https://github.com/peterhagelhof7-cmd/ihk-berichtsheft`


## Inhalt

1. [Anforderungen an die Instanz](#1-anforderungen-an-die-instanz)
2. [Quellcode holen und bauen](#2-quellcode-holen-und-bauen)
3. [App auf dem Server platzieren](#3-app-auf-dem-server-platzieren)
4. [App aktivieren](#4-app-aktivieren)
5. [Ausbilder-Gruppe anlegen](#5-ausbilder-gruppe-anlegen)
6. [Stammdaten einrichten](#6-stammdaten-einrichten)
7. [Azubis aktivieren und verwalten](#7-azubis-aktivieren-und-verwalten)
8. [Funktion prüfen](#8-funktion-prüfen)
9. [Laufender Betrieb](#9-laufender-betrieb)
10. [Häufige Probleme](#10-häufige-probleme)


## 1. Anforderungen an die Instanz

| Punkt | Anforderung | Warum |
|---|---|---|
| Nextcloud-Version | 30 oder neuer (getestet: 34.0.1) | `appinfo/info.xml` erklärt Kompatibilität bis NC 34 |
| PHP-Version | 8.2 oder neuer (getestet: 8.5.4) | `composer.json` verlangt `^8.2` |
| SSH-/`occ`-Zugriff | zwingend erforderlich | Migration, Gruppenanlage, Fehlersuche laufen über `occ`; ohne SSH ist praktisch keine der folgenden Schritte durchführbar |
| Composer + Node/npm | einmalig, zum Bauen | kann auch auf einem separaten Build-Rechner erfolgen (siehe Abschnitt 2) — auf dem Server selbst danach nicht mehr nötig |
| System-Cronjob | alle 5 Minuten, `cron.php` | ohne Cron laufen Erinnerungen, Ausbilder-Digest, PDF-Export und Lehrjahresabfrage nicht automatisch |
| SMTP-Mailversand | in Nextcloud eingerichtet | die App verschickt zusätzlich zu In-App-Benachrichtigungen auch E-Mails (u. a. die Zurückweisungs-Mail mit Ausbilder-Kommentar) |
| `overwrite.cli.url` gesetzt | auf die externe HTTPS-URL der Instanz (z. B. `https://berichtsheft.example.de`) | die Benachrichtigungs-E-Mails werden aus Hintergrundjobs (Cron) erzeugt — dort gibt es keinen Web-Request, aus dem Nextcloud den Hostnamen ableiten könnte. Ohne diesen Wert enthält der Link in der Mail einen internen Host-/Containernamen statt der echten Adresse und ist nicht anklickbar. Muss pro Instanz einmalig gesetzt werden (siehe Abschnitt 10) |
| Erlaubnis für eigene Apps | „nicht aus dem App Store"-Apps müssen aktivierbar sein | bei manchen verwalteten/gehosteten Angeboten eingeschränkt — im Zweifel beim Hoster erfragen |
| HTTPS mit gültigem Zertifikat | feste Domain/Subdomain, Let's-Encrypt reicht | Grundvoraussetzung jeder Nextcloud-Instanz |
| Datenschutz | echte personenbezogene Ausbildungsdaten (Name, Tätigkeitsbeschreibungen, Anwesenheit, Zeitstempel) | Serverstandort (EU empfohlen) und ggf. Auftragsverarbeitungsvertrag vorab klären |

Kein Datenbank-Sondersystem nötig — die App nutzt ausschließlich die von
Nextcloud selbst bereits verwaltete Datenbank (MySQL/MariaDB/PostgreSQL/
SQLite, je nachdem was die Instanz einsetzt) über Nextclouds eigene
Migrations-API.


## 2. Quellcode holen und bauen

Auf einem Rechner mit Composer und Node (kann der Server selbst sein,
oder ein separater Build-Rechner, von dem aus nur das fertige Ergebnis
per SFTP/SSH auf den Server kopiert wird):

```
git clone https://github.com/peterhagelhof7-cmd/ihk-berichtsheft.git berichtsheft
cd berichtsheft
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

**Hinweis:** Im Repository liegt bewusst keine `package-lock.json` und
keine `composer.lock` (siehe `.gitignore`) — `npm ci` funktioniert daher
NICHT (es benötigt zwingend eine vorhandene Lockdatei) und würde mit einer
Fehlermeldung abbrechen. `npm install` ist hier der richtige Befehl.

Nach `npm run build` liegen die fertigen Ordner `js/` und `css/` vor.

**Fertiges Release-Paket statt selbst bauen:** Für jede Version liegt auf
der GitHub-Releases-Seite
(`https://github.com/peterhagelhof7-cmd/ihk-berichtsheft/releases`) ein
bereits gebautes Release-Archiv als Anhang bei, z. B.
`berichtsheft-0.11.2.tar.gz`. Es enthält bereits `vendor/`, `js/` und
`css/`, kann also direkt entpackt und gemäß Abschnitt 3 platziert werden —
ganz ohne Composer/Node auf dem Zielsystem.


## 3. App auf dem Server platzieren

Den gesamten `berichtsheft`-Ordner (inkl. der erzeugten `vendor/`-,
`js/`- und `css/`-Ordner, aber OHNE `node_modules/`, `.git/`, `src/`,
`tests/`) in das Nextcloud-Apps-Verzeichnis kopieren, typischerweise:

```
/var/www/nextcloud/apps/berichtsheft/
```

oder, falls das Haupt-`apps`-Verzeichnis nicht beschreibbar ist:

```
/var/www/nextcloud/apps-extra/berichtsheft/
```

Rechte prüfen: die Dateien müssen dem Nextcloud-Webserver-Benutzer
gehören bzw. für ihn lesbar sein (typischerweise `www-data`).


## 4. App aktivieren

Als Nextcloud-Administrator per SSH auf dem Server:

```
cd /var/www/nextcloud
sudo -u www-data php occ app:enable berichtsheft
```

Dieser Schritt führt automatisch die Datenbank-Migration aus (legt alle
benötigten Tabellen — aktuell 11, alle mit Präfix `bh_` — und Spalten an;
die App bringt ihre Migrationen selbst mit, es ist keine manuelle
DB-Änderung nötig). Erfolg prüfen mit:

```
sudo -u www-data php occ app:list | grep berichtsheft
```


## 5. Ausbilder-Gruppe anlegen

Die App kennt keine feste Admin/Ausbilder-Unterscheidung, sondern nutzt
eine normale Nextcloud-Gruppe:

```
sudo -u www-data php occ group:add berichtsheft-ausbilder
sudo -u www-data php occ group:adduser berichtsheft-ausbilder <Benutzername>
```

(`berichtsheft-ausbilder` ist der Standard-Gruppenname. Ein anderer Name
kann später in den App-Einstellungen hinterlegt werden, siehe Schritt 7.)

Mehrere Ausbilder können der Gruppe hinzugefügt werden — alle Mitglieder
haben gleichberechtigten Zugriff auf alle Azubis (wichtig für
Krankheits-/Urlaubsvertretung).

**Wichtig:** Ein Nutzer, der Mitglied dieser Gruppe ist, kann in der App
nicht gleichzeitig als Azubi aktiviert werden (Dual-Rollen-Ausschluss, um
Selbst-Abnahme des eigenen Berichtshefts auszuschließen) — siehe
Abschnitt 7.


## 6. Stammdaten einrichten

Jedes Mitglied der Ausbilder-Gruppe sieht die Verwaltungsoberfläche direkt
in der Haupt-App — eine Delegation durch einen echten
Nextcloud-Administrator ist nicht nötig, die Sichtbarkeit hängt
ausschließlich an der Gruppenmitgliedschaft aus Schritt 5. Als Mitglied
der Ausbilder-Gruppe einloggen, über das „Berichtsheft"-App-Icon in der
oberen Leiste öffnen, im Menü „Verwaltung" auswählen und folgende Angaben
einmalig setzen:

- Ausbildungsbetrieb (rechtliche Firmierung) — erscheint auf dem
  Deckblatt jedes Azubi-Berichtshefts
- Betriebsadresse — ebenfalls Deckblatt-Pflichtfeld
- Ausbildungsjahr-Start (Format MM-TT, z. B. 09-01 für Bayern) — legt
  fest, ab welchem Kalendertag jedes Jahr ein neues Lehrjahr beginnt (in
  anderen Bundesländern kann das Datum abweichen)
- Nextcloud-Gruppenname für Ausbilder (falls von
  `berichtsheft-ausbilder` abweichend gewählt)

Anschließend können über dieselbe Oberfläche die Berufsschul-Fächer
angelegt und den jeweiligen Lehrjahren zugeordnet werden — optional je
Fach mit Unterrichtsinhalt-Freitext, den der Azubi später beim
Tageseintrag ausfüllen kann.

Ebenfalls in der Verwaltung liegt der **Berufe-Katalog**: Er ist ab Werk
mit den IT-Berufen nach AO2020 vorbelegt, kann aber frei ergänzt oder
bereinigt werden. Zusammen mit den frei pflegbaren Fächern lässt sich die
App so auch für andere Branchen einsetzen (Modell: eine Branche pro
Instanz — Standard-Fächer/-Berufe löschen, eigene anlegen). Ein Beruf
kann nicht gelöscht werden, solange noch ein Azubi ihn trägt.


## 7. Azubis aktivieren und verwalten

Für jeden Auszubildenden muss zunächst ein reguläres
Nextcloud-Benutzerkonto existieren (falls noch nicht vorhanden, wie
gewohnt über Einstellungen → Benutzer anlegen). Danach in der
Berichtsheft-Verwaltungsoberfläche (App-Icon → „Verwaltung"):

1. Den Benutzer aus der Liste wählen und „Als Azubi aktivieren"
   anklicken — **Hinweis:** Mitglieder der Ausbilder-Gruppe erscheinen
   hier bewusst nicht in der Auswahl (siehe Abschnitt 5)
2. Ausbildungsberuf auswählen — die Liste kommt aus dem pflegbaren
   Berufe-Katalog (ab Werk mit den IT-Berufen nach AO2020 vorbelegt, in
   der Verwaltung frei erweiter-/löschbar, siehe Abschnitt 6)
3. Ausbildungsstart (Datum) eintragen
4. „Ausbildungsjahr zu Beginn" — im Normalfall 1 stehen lassen; nur bei
   einem Azubi, der mitten in der Ausbildung den Betrieb gewechselt hat,
   auf das tatsächliche Ausbildungsjahr setzen
5. „Lehrjahr zu Beginn" — das aktuell für die Berufsschule geltende
   Lehrjahr (kann vom Ausbildungsjahr abweichen, z. B. bei Wiederholern)
6. Einen „Berichtsheft-Verantwortlichen" aus der Ausbilder-Gruppe
   zuweisen (bekommt die Benachrichtigung, wenn dieser Azubi eine Woche
   einreicht)
7. Optional: Ausbildungsabteilung eintragen

Der Azubi sollte anschließend selbst unter Einstellungen → Persönlich →
Berichtsheft seinen Vor- und Nachnamen eintragen (erscheint auf dem
Deckblatt) — das kann der Ausbilder nicht für ihn erledigen.

**Weitere Verwaltungsfunktionen** (in der gleichen Oberfläche, pro
aktivem Azubi):

- **Bearbeiten** — Stammdaten (Ausbildungsberuf, Status, Verantwortlicher
  usw.) nachträglich ändern
- **Beenden / Reaktivieren** — Ausbildung als beendet markieren (z. B.
  bei Abbruch oder Abschluss); beendete Azubis tauchen nicht mehr in der
  aktiven Wochenübersicht auf, können bei Bedarf reaktiviert werden
- **Deckblatt neu erzeugen** — erzeugt die Deckblatt-PDF neu (z. B. nach
  einer Namenskorrektur)
- **IHK-Gesamtnachweis erzeugen** — kombiniert Deckblatt und alle bereits
  akzeptierten Wochen zu einem einzigen PDF (max. 35 MB, IHK-Vorgabe),
  manuell durch einen Ausbilder ausgelöst. Die Datei landet im ohnehin
  bestehenden Ordner `Berichtsheft - <Nachname>, <Vorname>` und ist damit
  automatisch sowohl für den Azubi als auch für die Ausbilder-Gruppe
  einsehbar — keine gesonderte Freigabe nötig.


## 8. Funktion prüfen

- Als Azubi einloggen, unter „Berichtsheft" (App-Navigation) die aktuelle
  Woche mit Einträgen füllen und einreichen
- Als Ausbilder prüfen, dass eine Benachrichtigung ankommt und die Woche
  in der Prüfen-Ansicht erscheint (inkl. aller Tageseinträge je Fach)
- Woche akzeptieren oder mit Kommentar zurückweisen, jeweils prüfen ob
  der Azubi benachrichtigt wird (In-App und E-Mail)
- Alle 4 Wochen sollte automatisch ein PDF im Nextcloud-Dateibereich des
  Azubis erscheinen, sobald alle 4 Wochen akzeptiert sind (Ordner
  „Berichtsheft - <Nachname>, <Vorname>")
- Deckblatt prüfen: sollte bereits direkt nach der Azubi-Aktivierung als
  eigene Datei (`<Nachname> <Vorname>-00.pdf`) existieren
- IHK-Gesamtnachweis testweise erzeugen und die Seitenreihenfolge
  kontrollieren (Deckblatt, dann Nachweis Nr. 1, 2, 3, … in aufsteigender
  Nummerierung)

Für gezieltes Testen der drei zeitgesteuerten Hintergrundjobs, ohne auf
den echten Wochentag/Termin zu warten, siehe Abschnitt 10.


## 9. Laufender Betrieb

- **Updates**: siehe den eigenen Abschnitt „App aktualisieren" unten.
- **Backups**: die App legt keine eigenen Dateien außerhalb der
  normalen Nextcloud-Datenbank und des normalen Nextcloud-Dateibereichs
  an — ein reguläres Nextcloud-Backup (Datenbank + `data`-Verzeichnis)
  deckt auch alle Berichtsheft-Daten mit ab
- **Automatisierte Tests** (optional, für Entwickler/CI, nicht für den
  produktiven Server nötig): `composer install` (inkl. Dev-Abhängigkeiten,
  NICHT auf einer live bedienten Instanz ausführen — siehe Warnhinweis
  unten) und danach `composer test:unit`


### App aktualisieren (Update auf eine neue Version)

Ein Update ersetzt nur das App-Verzeichnis und lässt anschließend
Nextcloud die fälligen Datenbank-Migrationen ausführen. Bestehende Daten
(Azubis, Wochen, Noten, PDFs) bleiben erhalten — sie liegen in der
Nextcloud-Datenbank bzw. im normalen Dateibereich, nicht im App-Ordner.

1. **Backup** ziehen (Datenbank + `data`-Verzeichnis) — ein reguläres
   Nextcloud-Backup genügt. Bei Produktivinstanzen zwingend vor jedem
   Update.
2. **Neue Version beschaffen** — entweder das fertige Release-Archiv der
   Zielversion von der GitHub-Releases-Seite herunterladen
   (`berichtsheft-<version>.tar.gz`, enthält bereits `vendor/js/css`),
   oder aus Git selbst bauen: `git pull`, dann
   `composer install --no-dev --optimize-autoloader` und
   `npm install && npm run build` (wie in Abschnitt 2).
3. **Optional Wartungsmodus** an, damit während des Austauschs niemand
   schreibt:
   ```
   sudo -u www-data php occ maintenance:mode --on
   ```
4. **App-Verzeichnis ersetzen** — den Inhalt von
   `/var/www/nextcloud/apps/berichtsheft/` durch den neuen Stand ersetzen
   (altes Verzeichnis wegsichern/leeren und neuen Stand hineinlegen bzw.
   das Release-Archiv darüber entpacken). `vendor/`, `js/` und `css/`
   müssen mitkommen (Laufzeit-nötig, u. a. für die PDF-Erzeugung).
   Danach Rechte zurücksetzen:
   ```
   sudo chown -R www-data:www-data /var/www/nextcloud/apps/berichtsheft
   ```
5. **Migrationen/Upgrade ausführen**:
   ```
   sudo -u www-data php occ upgrade
   ```
   (Laufen ansonsten beim nächsten Seitenaufruf automatisch an.)
6. **Wartungsmodus aus**:
   ```
   sudo -u www-data php occ maintenance:mode --off
   ```
7. **Erfolg prüfen** — die neue Versionsnummer sollte erscheinen:
   ```
   sudo -u www-data php occ app:list | grep berichtsheft
   ```

Hinweise:
- **Kein Downgrade**: Nextcloud unterstützt das Herabstufen einer App-
  Version nicht. Zurück geht es nur über das Backup aus Schritt 1.
- Neue Instanz-weite Einstellungen, die eine Version evtl. voraussetzt
  (z. B. `overwrite.cli.url` aus Abschnitt 1), bleiben über Updates
  hinweg bestehen und müssen nur einmalig gesetzt werden.
- Die Migrationen sind idempotent (prüfen auf bereits vorhandene
  Tabellen/Spalten) — ein erneuter `occ upgrade` schadet nicht.


## 10. Häufige Probleme

- **Erinnerungen/Digest/Export kommen nicht automatisch**: Cronjob
  prüfen (`occ background-job:list` zeigt registrierte Jobs,
  `occ background-job:execute <id> --force-execute` erzwingt einen
  sofortigen Lauf, umgeht aber nicht die eingebaute
  Wochentag/Uhrzeit-Logik der drei zeitgesteuerten Jobs — dafür siehe
  den Debug-Befehl unten)
- **Zum gezielten Testen** ohne auf einen echten Montag/Stichtag zu
  warten:

  ```
  occ berichtsheft:debug-run-job weekly-reminder --ignoriere-zeitfenster
  occ berichtsheft:debug-run-job ausbilder-digest --ignoriere-zeitfenster
  occ berichtsheft:debug-run-job lehrjahr-abfrage --ignoriere-zeitfenster
  ```

- **Keine E-Mails**: SMTP-Konfiguration in den Nextcloud-Grundeinstellungen
  prüfen (Testmail von dort verschicken)
- **Mail-Benachrichtigung enthält einen unbrauchbaren Link** (interner
  Host-/Containername statt der echten Adresse, z. B.
  `http://mein-nextcloud-container/index.php/apps/berichtsheft/`): Die
  App erzeugt den Link korrekt über Nextclouds URL-Generator; die falsche
  Adresse stammt aus der Server-Einstellung `overwrite.cli.url`, die
  Nextcloud in Hintergrundjobs (Cron, kein Web-Request) als Basis-URL
  verwendet. Auf die echte externe URL setzen:

  ```
  sudo -u www-data php occ config:system:set overwrite.cli.url --value "https://<externe-domain>"
  sudo -u www-data php occ config:system:get overwrite.cli.url
  ```

  Das ist eine **Server-Konfiguration pro Instanz** (nicht Teil der App)
  und muss auf jedem neuen System einmalig gesetzt werden. Ohne Trailing-
  Slash eintragen. Der Wert wirkt sich auch auf andere aus Hintergrund-
  jobs erzeugte Links aus (nicht nur auf das Berichtsheft).
- **Ausbilder sieht die Verwaltungsoberfläche nicht**: der Benutzer ist
  vermutlich nicht Mitglied der in Schritt 5 angelegten (bzw. in Schritt 6
  ggf. umbenannten) Ausbilder-Gruppe — mit `occ group:list` prüfen
- **Ein Ausbilder soll als Azubi aktiviert werden**: geht absichtlich
  nicht (weder über die Oberfläche noch direkt über die API) — Mitglieder
  der Ausbilder-Gruppe müssten dafür zunächst aus der Gruppe entfernt
  werden
- **`npm ci` schlägt fehl**: siehe Hinweis in Abschnitt 2 — `npm install`
  verwenden, da bewusst keine `package-lock.json` im Repository liegt

**Warnhinweis für Entwickler/Betreuer der App:** `composer install` bzw.
`composer update` OHNE `--no-dev` NIEMALS im Verzeichnis einer live
bedienten Nextcloud-Instanz ausführen — die dabei zusätzlich geladenen
Nextcloud-API-Stub-Pakete (`nextcloud/ocp`) können mit der tatsächlich
installierten Nextcloud-Version kollidieren und die App (Web und `occ`
gleichermaßen) mit einem PHP-Fatal-Error lahmlegen. Tests immer nur in
einer separaten Checkout-Kopie ausführen, nie im produktiven
App-Verzeichnis.
