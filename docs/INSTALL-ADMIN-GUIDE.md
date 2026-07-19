# Installations- und Administrationsleitfaden — Berichtsheft

Zielgruppe: der Systemverwalter, der die App auf einer echten
Nextcloud-Instanz einrichtet und danach betreut.

**Stand dieses Dokuments:** Release Candidate **0.9.0**. Die App wurde
umfangreich auf einer lokalen Nextcloud-34.0.1-Testinstanz (WSL2) geprüft:
kompletter Azubi/Ausbilder-Workflow per echter Browser-Bedienung, alle
Hintergrundjobs per `occ`, E-Mail-Versand über einen echten SMTP/IMAP-
Account, PDF-Ausgabe visuell per Rendering geprüft, 31 automatisierte
PHPUnit-Tests. **Noch nicht geprüft:** Verhalten auf der tatsächlichen
Zielinstanz (anderer Server, andere PHP-/DB-Version, echte Nutzer). Vor
dem produktiven Einsatz Abschnitt 8 (Funktion prüfen) einmal vollständig
durchgehen.

Repository: `https://github.com/peterhagelhof7-cmd/ihk-berichtsheft` (privat)


## Inhalt

1. [Anforderungen an die Instanz](#1-anforderungen-an-die-instanz)
2. [Quellcode holen und bauen](#2-quellcode-holen-und-bauen)
3. [App auf dem Server platzieren](#3-app-auf-dem-server-platzieren)
4. [App aktivieren](#4-app-aktivieren)
5. [Ausbilder-Gruppe anlegen](#5-ausbilder-gruppe-anlegen)
6. [Delegation der Admin-Oberfläche](#6-delegation-der-admin-oberfläche-wichtiger-manueller-schritt)
7. [Stammdaten einrichten](#7-stammdaten-einrichten)
8. [Azubis aktivieren und verwalten](#8-azubis-aktivieren-und-verwalten)
9. [Funktion prüfen](#9-funktion-prüfen)
10. [Laufender Betrieb](#10-laufender-betrieb)
11. [Häufige Probleme](#11-häufige-probleme)


## 1. Anforderungen an die Instanz

| Punkt | Anforderung | Warum |
|---|---|---|
| Nextcloud-Version | 30 oder neuer (getestet: 34.0.1) | `appinfo/info.xml` erklärt Kompatibilität bis NC 34 |
| PHP-Version | 8.2 oder neuer (getestet: 8.5.4) | `composer.json` verlangt `^8.2` |
| SSH-/`occ`-Zugriff | zwingend erforderlich | Migration, Gruppenanlage, Delegation, Fehlersuche laufen über `occ`; ohne SSH ist praktisch keine der folgenden Schritte durchführbar |
| Composer + Node/npm | einmalig, zum Bauen | kann auch auf einem separaten Build-Rechner erfolgen (siehe Abschnitt 2) — auf dem Server selbst danach nicht mehr nötig |
| System-Cronjob | alle 5 Minuten, `cron.php` | ohne Cron laufen Erinnerungen, Ausbilder-Digest, PDF-Export und Lehrjahresabfrage nicht automatisch |
| SMTP-Mailversand | in Nextcloud eingerichtet | die App verschickt zusätzlich zu In-App-Benachrichtigungen auch E-Mails (u. a. die Zurückweisungs-Mail mit Ausbilder-Kommentar) |
| Erlaubnis für eigene Apps | „nicht aus dem App Store"-Apps müssen aktivierbar sein | bei manchen verwalteten/gehosteten Angeboten eingeschränkt — im Zweifel beim Hoster erfragen |
| HTTPS mit gültigem Zertifikat | feste Domain/Subdomain, Let's-Encrypt reicht | Grundvoraussetzung jeder Nextcloud-Instanz |
| Administrator-Zugang | eigener Account, getrennt vom SSH-Zugang | für Schritt 6 (Delegation) zwingend ein echter Nextcloud-Administrator nötig |
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

**Fertiges Release-Paket statt selbst bauen:** Alternativ liegt im
Repository ein bereits gebautes Release-Archiv bei,
`dist/berichtsheft-0.9.0-rc1.tar.gz` (falls vorhanden — sonst wie oben
selbst bauen). Es kann direkt entpackt und gemäß Abschnitt 3 platziert
werden, ganz ohne Composer/Node auf dem Zielsystem.


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
Tabellen an, aktuell 10 Stück plus die beiden in diesem RC nachgezogenen
Spalten `bh_azubi.status` und `bh_fach_eintrag.inhalt`). Erfolg prüfen
mit:

```
sudo -u www-data php occ app:list | grep berichtsheft
```


## 5. Ausbilder-Gruppe anlegen

Die App kennt keine feste Admin/Ausbilder-Unterscheidung, sondern nutzt
eine normale Nextcloud-Gruppe:

```
sudo -u www-data php occ group:add berichtsheft-ausbilder
sudo -u www-data php occ group:add-member berichtsheft-ausbilder <Benutzername>
```

(`berichtsheft-ausbilder` ist der Standard-Gruppenname. Ein anderer Name
kann später in den App-Einstellungen hinterlegt werden, siehe Schritt 7.)

Mehrere Ausbilder können der Gruppe hinzugefügt werden — alle Mitglieder
haben gleichberechtigten Zugriff auf alle Azubis (wichtig für
Krankheits-/Urlaubsvertretung).

**Wichtig:** Ein Nutzer, der Mitglied dieser Gruppe ist, kann in der App
nicht gleichzeitig als Azubi aktiviert werden (Dual-Rollen-Ausschluss, um
Selbst-Abnahme des eigenen Berichtshefts auszuschließen) — siehe
Abschnitt 8.


## 6. Delegation der Admin-Oberfläche (wichtiger manueller Schritt)

Damit die Ausbilder-Gruppe die Berichtsheft-Verwaltungsoberfläche (Azubis
aktivieren, Fächer pflegen, Stammdaten setzen) OHNE vollen
Nextcloud-Administrator-Status sehen kann, muss ein echter
Nextcloud-Administrator diese Einstellungsseite einmalig an die Gruppe
delegieren:

1. Als Administrator in der Nextcloud-Weboberfläche einloggen
2. Einstellungen → Administration → Grundeinstellungen
3. Dort erscheint „Berichtsheft" in der Liste der delegierbaren
   Einstellungsbereiche
4. Die Gruppe `berichtsheft-ausbilder` (oder den gewählten Gruppennamen)
   dort eintragen

Dieser Schritt kann NICHT automatisch durch die App erledigt werden — er
muss nach jeder Installation einmal manuell durch einen echten
Administrator erfolgen.


## 7. Stammdaten einrichten

Als Mitglied der Ausbilder-Gruppe einloggen, zur
Berichtsheft-Verwaltungsoberfläche navigieren (Einstellungen →
Administration → Berichtsheft, nach Schritt 6 sichtbar) und folgende
Angaben einmalig setzen:

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


## 8. Azubis aktivieren und verwalten

Für jeden Auszubildenden muss zunächst ein reguläres
Nextcloud-Benutzerkonto existieren (falls noch nicht vorhanden, wie
gewohnt über Einstellungen → Benutzer anlegen). Danach in der
Berichtsheft-Verwaltungsoberfläche:

1. Den Benutzer aus der Liste wählen und „Als Azubi aktivieren"
   anklicken — **Hinweis:** Mitglieder der Ausbilder-Gruppe erscheinen
   hier bewusst nicht in der Auswahl (siehe Abschnitt 5)
2. Ausbildungsberuf auswählen (die sechs neugeordneten IT-Berufe nach
   AO2020)
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


## 9. Funktion prüfen

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
den echten Wochentag/Termin zu warten, siehe Abschnitt 11.


## 10. Laufender Betrieb

- **Updates**: neue Version aus Git ziehen, erneut `composer install
  --no-dev --optimize-autoloader` und `npm install && npm run build`
  ausführen, Dateien auf dem Server ersetzen, danach
  `sudo -u www-data php occ upgrade` bzw. beim nächsten Seitenaufruf
  laufen anstehende Migrationen automatisch
- **Backups**: die App legt keine eigenen Dateien außerhalb der
  normalen Nextcloud-Datenbank und des normalen Nextcloud-Dateibereichs
  an — ein reguläres Nextcloud-Backup (Datenbank + `data`-Verzeichnis)
  deckt auch alle Berichtsheft-Daten mit ab
- **Automatisierte Tests** (optional, für Entwickler/CI, nicht für den
  produktiven Server nötig): `composer install` (inkl. Dev-Abhängigkeiten,
  NICHT auf einer live bedienten Instanz ausführen — siehe Warnhinweis
  unten) und danach `composer test:unit`


## 11. Häufige Probleme

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
- **Ausbilder sieht die Verwaltungsoberfläche nicht**: Schritt 6
  (Delegation) wurde vermutlich übersprungen, oder der Benutzer ist nicht
  Mitglied der in Schritt 7 hinterlegten Gruppe
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
