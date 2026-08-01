# Berichtsheft — IHK-konformes digitales Ausbildungsnachweis für Nextcloud

Digitales Berichtsheft (Ausbildungsnachweis) für Auszubildende — mitgeliefert
für die neugeordneten IT-Berufe nach AO2020, durch pflegbare Berufe und Fächer
aber auch für andere Ausbildungsberufe/Branchen nutzbar — als selbst gehostete
Nextcloud-App,
nicht als SaaS. Ersetzt handschriftliche oder lose PDF-Wochenberichte durch
einen echten Einreichen/Prüfen-Workflow direkt in der Nextcloud-Instanz des
Ausbildungsbetriebs: Azubis tragen täglich ihre Tätigkeiten ein, reichen
die Woche zur Prüfung ein, ein Ausbilder akzeptiert oder weist mit
Kommentar zurück. Alle vier Wochen entsteht automatisch ein
mehrseitiges, IHK-konformes PDF — inklusive Deckblatt und optionalem
Firmenlogo.

Jeder Betrieb installiert die App eigenständig in seiner eigenen
Nextcloud-Instanz; es gibt keinen zentralen Dienst und keine
Drittanbieter-Cloud, die Ausbildungsdaten sehen.


## Warum

IHK-Berichtshefte werden in der Praxis oft in Excel, Word oder auf Papier
geführt — mit allen bekannten Problemen: Versionschaos, vergessene Wochen,
keine durchgängige Nummerierung, kein einfacher Prüf-Workflow zwischen
Azubi und Ausbilder. Diese App bildet den kompletten Ablauf digital ab und
nutzt dafür eine Infrastruktur, die viele Ausbildungsbetriebe ohnehin schon
betreiben oder betreiben könnten: Nextcloud.


## Funktionsumfang

- **Tägliche Einträge**: Betrieb (Freitext), Berufsschule (je Fach mit
  Stunden + optionalem Inhalt), Feiertag/Urlaub/Krankheit, optional
  Samstag/Sonntag.
- **Einreichen/Prüfen-Workflow**: Azubi reicht eine Woche ein, ein
  Ausbilder akzeptiert oder weist mit Kommentar zur Korrektur zurück. Alle
  Ausbilder einer Instanz sind gleichberechtigt (Vertretungsfall); ein pro
  Azubi festgelegter Berichtsheft-Verantwortlicher erhält die
  Einreichungs-Benachrichtigung.
- **Korrekte, kalenderbasierte Nachweis-Nummerierung**: Nachweis Nr. 1 ist
  immer die erste Kalenderwoche ab dem gesetzten Ausbildungsstart —
  unabhängig davon, in welcher Reihenfolge Wochen tatsächlich ausgefüllt
  werden.
- **Automatischer 4-Wochen-PDF-Export**, sobald alle vier Wochen eines
  Bündels akzeptiert sind, inklusive einmaligem IHK-Deckblatt (Name,
  Betriebsadresse, Ausbildungsberuf/Fachrichtung, Ausbildungsbetrieb,
  verantwortlicher Ausbilder).
- **IHK-Gesamtnachweis**: ein zusammengeführtes PDF über alle bislang
  akzeptierten Wochen eines Azubis, manuell durch einen Ausbilder
  auslösbar.
- **Eigenes Firmenlogo**: Ausbilder wählen ein Bild aus den eigenen
  Nextcloud-Dateien — kein separater Upload nötig. Erscheint groß auf dem
  Deckblatt und verkleinert in einer Ecke jedes Wochennachweises.
- **Notenverwaltung**: Prüfungsnoten je Fach/Lehrjahr mit gewichtetem
  Notenschnitt, als eigenes archivierbares PDF sowie als stets aktuelle
  Übersicht.
- **Pflegbare Ausbildungsberufe & Fächer**: Ausbilder legen eigene Berufe
  (Bezeichnung + optionale Fachrichtung) und Berufsschul-Fächer selbst an,
  bearbeiten und löschen sie. Die mitgelieferten IT-Berufe/-Fächer lassen
  sich ersetzen — so ist das Berichtsheft auch für **andere Branchen**
  nutzbar, nicht nur für die IT-Berufe.
- **Lehrjahr-Zuweisung** und Azubi-Status (aktiv/Ausbildung beendet, z. B.
  bei Berufswechsel oder Abbruch), ohne Verlauf zu löschen.
- **Erinnerungen & Digest**: wöchentliche Erinnerung an Azubis mit
  offenen/nicht eingereichten Wochen, Sammel-Digest an Ausbilder mit
  ausstehenden Prüfungen — pro Nutzer in den persönlichen Einstellungen
  konfigurierbar.
- **Zugriffsmodell über Nextcloud-Bordmittel**: PDFs liegen im eigenen
  Dateibereich des Azubis, ein Gruppen-Share stellt sie automatisch allen
  Ausbildern read-only bereit — keine zusätzliche Berechtigungsverwaltung.


## Beispiel-Ausgaben

Die App bringt einen `occ berichtsheft:seed-demo`-Befehl mit, der
Demo-Azubis mit vollständigem, plausiblem Verlauf (Einträge, Noten,
mehrere Nachweis-Bündel) anlegt — praktisch zum Ausprobieren ohne echte
Ausbildungsdaten. So sehen die daraus erzeugten PDFs aus:

| Datei | Inhalt |
|---|---|
| [Deckblatt (Lena Sommer, 1. Lehrjahr)](docs/beispiele/sommer-lena-deckblatt.pdf) | Einmaliges IHK-Deckblatt |
| [Wochennachweis-Bündel KW32/2025 (Lena Sommer)](docs/beispiele/sommer-lena-wochennachweis-kw32-2025.pdf) | 4-Wochen-Export |
| [Deckblatt mit Firmenlogo (Tom Berger, 2. Lehrjahr)](docs/beispiele/berger-tom-deckblatt.pdf) | Deckblatt mit eingebundenem Logo |
| [Wochennachweis-Bündel KW32/2024 (Tom Berger)](docs/beispiele/berger-tom-wochennachweis-kw32-2024.pdf) | 4-Wochen-Export |
| [Gesamtnachweis (Tom Berger)](docs/beispiele/berger-tom-gesamtnachweis.pdf) | Alle akzeptierten Wochen zusammengeführt |
| [Notenschnitt aktuell (Tom Berger)](docs/beispiele/berger-tom-notenschnitt.pdf) | Notenübersicht mit gewichtetem Schnitt |


## Voraussetzungen

| Punkt | Anforderung |
|---|---|
| Nextcloud | 30 oder neuer (getestet: 34.0.1) |
| PHP | 8.2 oder neuer (getestet: 8.5.4) |
| Build-Werkzeuge (einmalig) | Composer, Node.js/npm — nur zum Bauen, auf dem Zielserver selbst nicht nötig |

Ausführliche Installationsanleitung: [docs/INSTALL-ADMIN-GUIDE.md](docs/INSTALL-ADMIN-GUIDE.md).


## Entwicklung

```bash
composer install
npm install
npm run build      # erzeugt js/ und css/
php vendor/bin/phpunit tests/
```

Die App folgt der üblichen Nextcloud-App-Struktur (`lib/` = PHP-Backend
über das App Framework, `src/` = Vue-3-Frontend mit `@nextcloud/vue`,
`templates/pdf/` = serverseitig mit [Dompdf](https://github.com/dompdf/dompdf)
gerenderte PDF-Vorlagen).


## Dokumentation

- [Installations- und Administrationsleitfaden](docs/INSTALL-ADMIN-GUIDE.md)
- [Handbuch für Ausbilder](docs/HANDBUCH-AUSBILDER.pdf)
- [Handbuch für Azubis](docs/HANDBUCH-AZUBI.pdf)
- [Ein-Seiten-Überblick](docs/ONEPAGER.pdf)


## Lizenz

[AGPL-3.0-or-later](https://www.gnu.org/licenses/agpl-3.0.html)
