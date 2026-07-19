# Übergabe — Berichtsheft-App

**Stand: Phasen 1–8 vollständig geschrieben.** Nur Phase 0/9 (Umgebung
einrichten, tatsächlich bauen/testen, paketieren) fehlt noch — die ist
blockiert, bis Peters Nextcloud-Instanz steht.

## Wo alles steht

**Maßgeblicher Plan**: `C:\Users\Admin\.claude\plans\merry-booping-swing.md`
— vollständig, vom Nutzer freigegeben. Kleine Korrektur bereits nachgezogen:
Tabelle `bh_ausbilder_digest_praeferenz` (30 Zeichen) hätte mit `oc_`-Präfix
das Oracle-Limit gesprengt, umbenannt zu `bh_digest_praeferenz`.

**Projektordner**: `C:\Users\Admin\ihk-berichtsheft\` — kein Git-Repo
bisher. Kein PHP/Composer/Docker lokal installiert (nur Node.js) — der
gesamte Code ist ungetestete Textform, jede einzelne verwendete OCP-Methode
wurde aber gegen echte, per WebFetch/curl abgerufene Nextcloud-Quelldateien
verifiziert (aktuelle `nextcloud/app_template`, `nextcloud/notes`,
`nextcloud/deck`, `nextcloud/server`-OCP-Interfaces, `nextcloud/theming`
für Personal-Settings).

**Anforderungen an die Instanz**: `anforderungen-nextcloud-instanz.txt`
bereits an Peter geschickt. Seine Anmerkungen: SMTP richtet er selbst ein,
generische Domain mit Let's Encrypt reicht, zwei Admin-Accounts geplant
(er + der KI-Agent). Auf die Instanz selbst warten wir noch.

## Task-Liste (TaskList-Tool, IDs #35-44)

| # | Phase | Status |
|---|---|---|
| 35 | Anforderungen-Datei | ✅ |
| 36 | Phase 1 — App-Grundgerüst | ✅ |
| 37 | Phase 2 — DB-Schema (10 Tabellen) | ✅ |
| 38 | Phase 3 — Admin-Oberfläche | ✅ |
| 39 | Phase 4 — Persönliche Angaben & Tageseinträge | ✅ |
| 40 | Phase 5 — Benachrichtigungen | ✅ |
| 41 | Phase 6 — Background-Jobs | ✅ |
| 42 | Phase 7 — PDF-Export | ✅ |
| 43 | Phase 8 — Prüfen-Workflow | ✅ |
| 44 | Phase 0/9 — Umgebung + Paketierung | ⬜ blockiert auf Nextcloud-Zugriff |

## Was als Nächstes ansteht (sobald die Instanz steht)

1. Zugriffsdetails klären (SSH/occ ja/nein, PHP-/Nextcloud-Version,
   Composer verfügbar) — Plan Abschnitt 5/8.
2. Docker-Dev-Weg (`juliusknorr/nextcloud-docker-dev` o. ä.) NUR als
   Fallback, falls kein direkter Zugriff auf die echte Instanz reicht —
   sonst direkt gegen die echte Instanz bauen/deployen.
3. `composer install`, `npm install && npm run build` — hier werden
   vermutlich die ersten echten Fehler auftauchen (Typos, kleinere
   API-Abweichungen), die ohne echte PHP/Node-Umgebung nicht auffindbar
   waren. Systematisch durcharbeiten.
4. `php occ app:enable berichtsheft`, Migration laufen lassen, dann Plan
   Abschnitt 7 (vollständige Verifikations-Checkliste) durchgehen -
   insbesondere:
   - Als echter Nextcloud-Admin die `AdminSettings`-Seite einmalig manuell
     an die `berichtsheft-ausbilder`-Gruppe delegieren (Settings →
     Administration → Basiseinstellungen) — das ist NICHT programmatisch
     durch die App erzwingbar, siehe Docblock in
     `lib/Settings/AdminSettings.php`.
   - dompdf-Rendering wird vermutlich nicht pixelgenau der `.doc`-Vorlage
     entsprechen — visuelle Nachjustierung von
     `templates/pdf/nachweis-woche.php`/`deckblatt.php` einplanen.

## Bekannte, bewusst nicht geschlossene Lücken (kein Fehler, sondern Scope-Grenze)

- `IUserManager::callForAllUsers()` (in `AzubiController::index()`) kann
  bei sehr vielen Nextcloud-Nutzern langsam werden — für den erwarteten
  Umfang (ein Betrieb, überschaubare Azubi-/Mitarbeiterzahl) unkritisch,
  bei Bedarf später auf Pagination umstellen.
- `PdfExportService`/`DeckblattService` haben keine Bild-Assets (Logo etc.)
  eingebaut — reiner Text/Tabellen-Aufbau passend zur Formularvorlage.
- Kein automatisiertes PHPUnit/Vitest existiert noch (Plan Abschnitt 7
  nennt PHPUnit-Tests als Teil der Verifikation) — erst sinnvoll, sobald
  eine echte PHP-Umgebung verfügbar ist.

## Wichtige verifizierte OCP-Fakten (Referenz, nicht erneut nachschlagen)

- `IManager::notify()` (Notifications) ist NICHT direkt in
  `OCP\Notification\IManager.php` aufgelistet, sondern über `IApp::notify()`
  geerbt (`IManager extends IApp, IPreloadableNotifier`) — eine erste
  WebFetch-Zusammenfassung hatte das übersehen, erst `grep` auf den
  Rohtext + Prüfung der `extends`-Klausel hat's aufgedeckt. **Lehre**: bei
  Unstimmigkeiten grep auf den Rohtext, nicht nur der KI-Zusammenfassung
  von WebFetch vertrauen.
- `IPersonalSettings` existiert NICHT als Interface — persönliche
  Einstellungen nutzen dasselbe `ISettings` wie Admin-Einstellungen (nur
  ohne die Delegations-Fähigkeit von `IDelegatedSettings`), Sektion via
  `IIconSection` wie bei Admin. Verifiziert an `nextcloud/server`s
  eigener `theming`-App (`apps/theming/lib/Settings/Personal.php` +
  `PersonalSection.php`).
- `IAppManager` liegt unter `OCP\App\IAppManager`, NICHT `OCP\IAppManager`
  — Tippfehler in einer ersten Fassung von `DeckblattService.php` schon
  korrigiert.
- `Util::linkToPath` existiert nicht — für App-Icon-Pfade
  `IURLGenerator::imagePath(string $appName, string $file): string`
  verwenden.
- `IUserManager::search()` ist deprecated (seit 27.0.0) — stattdessen
  `callForAllUsers(\Closure $callback, $search = '')` oder
  `searchDisplayName()`.
- `QBMapper` bringt kein eingebautes `find($id)` mit — in jedem Mapper
  selbst ergänzt, wo gebraucht (`AzubiMapper`, `FachMapper`, `WocheMapper`
  haben es; alle `->find()`-Aufrufe im gesamten Code wurden am Ende
  gegengeprüft, dass die jeweilige Methode existiert).
- Migrations-Pattern 1:1 aus einer echten `nextcloud/deck`-Migration
  übernommen (`createTable()`/`addColumn()`/`addIndex()`/
  `addUniqueIndex()`/`setPrimaryKey()`).
- `TimedJob`: Konstruktor ruft `parent::__construct($time); $this->
  setInterval($sekunden);`, `run($argument)` ist `protected` (nicht
  `public`) — verifiziert an einer echten Nextcloud-Core-App
  (`apps/user_status/lib/BackgroundJob/ClearOldStatusesBackgroundJob.php`).
- `occ`-Commands: `extends Symfony\Component\Console\Command\Command`,
  `configure()`/`execute()`, verifiziert an `nextcloud/deck`.
- Datei-Ablage: `IRootFolder::getUserFolder($userId)`,
  `Folder::newFolder()`/`get()`/`newFile()`, `File::putContent()`. Gruppen-
  Shares: `IShareManager::newShare()` → `setNode()`/`setShareType(IShare::
  TYPE_GROUP)`/`setSharedWith()`/`setSharedBy()`/`setShareOwner()`/
  `setPermissions(Constants::PERMISSION_READ)` → `createShare()`.
- `#[FrontpageRoute(verb: ..., url: ...)]`-Attribute-Routing (statt
  `appinfo/routes.php`) für alle eigenen internen AJAX-Endpunkte
  verwendet — bewusst nicht `#[ApiRoute]`, dessen genaues
  URL-Präfix-Verhalten nicht verifiziert wurde.
- `IInitialState::provideInitialState()` (PHP) + `loadState()` aus
  `@nextcloud/initial-state` (Vue) für Server→Client-Rollendaten
  (`istAusbilder`, `istAzubi`, `azubiDaten`, `digestPraeferenz`) statt
  eines zusätzlichen API-Rundlaufs beim Laden der Seite.

## Struktur-Überblick (was in welcher Datei liegt)

- `lib/Db/` — 10 Entity+Mapper-Paare + `Version0100Date20260719000000.php`
  (Migration, alle 10 Tabellen).
- `lib/Service/` — `AusbilderGruppenService`, `EintragService`,
  `StammdatenService`, `AusbildungsberufHelper`, `MailService`,
  `WocheStatusService`, `FileStorageService`, `DeckblattService`,
  `PdfExportService`.
- `lib/Controller/` — `PageController`, `AzubiController`,
  `FachController`, `LehrjahrController`, `StammdatenController`,
  `EintragController`, `PersoenlicheAngabenController`,
  `DigestPraeferenzController`, `PruefungController`.
- `lib/BackgroundJob/` — alle 5 Jobs aus Plan Abschnitt 3.
- `lib/Command/DebugRunJob.php` — `occ berichtsheft:debug-run-job
  {weekly-reminder|ausbilder-digest|lehrjahr-abfrage} [--ignoriere-
  zeitfenster] [--heute=Y-m-d]`.
- `lib/Settings/`, `lib/Notification/Notifier.php`.
- `templates/pdf/{deckblatt,nachweis-woche}.php` — dompdf-Templates.
- `src/views/` — `AdminSettings`, `FaecherVerwaltung`,
  `LehrjahrZuweisung`, `PersoenlicheAngaben`, `Wochenansicht`, `Pruefung`.
- `src/components/` — `TagEintrag`, `FachZeile`.
- `src/App.vue` — Rollenabhängiges Umschalten zwischen Wochenansicht
  (Azubi) und Prüfung (Ausbilder), beide gleichzeitig möglich falls jemand
  beide Rollen hat.
