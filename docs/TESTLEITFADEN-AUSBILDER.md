# Testleitfaden — Berichtsheft (für Ausbilder)

Dieser Leitfaden führt einmal komplett durch die Ausbilder-Seite der
neuen Berichtsheft-App. Ziel ist es, mit echten Azubis den kompletten
Ablauf durchzuspielen und alles zu notieren, was nicht wie erwartet
funktioniert. Bitte parallel den Testleitfaden für Azubis an die
Auszubildenden weitergeben — die meisten Tests hier setzen voraus, dass
mindestens ein Azubi zeitgleich seinen eigenen Test durchführt.

**Rolle:** Du benötigst einen Nextcloud-Account, der Mitglied der
Ausbilder-Gruppe (`berichtsheft-ausbilder`) ist. Falls das noch nicht
eingerichtet ist, den Systemverwalter darum bitten (siehe
`docs/INSTALL-ADMIN-GUIDE.md`, Abschnitt 5/6).


## Test 1 — Stammdaten einrichten (nur einmalig nötig)

1. Einstellungen → Administration → Berichtsheft öffnen (erscheint hier
   nur, wenn die Delegation bereits vom Systemverwalter eingerichtet
   wurde)
2. Ausbildungsbetrieb, Betriebsadresse und Ausbildungsjahr-Start prüfen
   bzw. eintragen
3. Prüfen: Erscheinen diese Angaben später korrekt auf dem Deckblatt
   eines Azubis?
4. Berufsschul-Fächer anlegen und den passenden Lehrjahren zuordnen
5. Prüfen: Kann ein Fach auch wieder bearbeitet oder entfernt werden?


## Test 2 — Azubi aktivieren

1. In derselben Verwaltungsoberfläche einen bestehenden
   Nextcloud-Nutzer aus der Liste wählen und als Azubi aktivieren
2. Ausbildungsberuf, Ausbildungsstart, Ausbildungsjahr/Lehrjahr zu
   Beginn eintragen, dich selbst (oder einen Kollegen) als
   „Berichtsheft-Verantwortlichen" zuweisen
3. **Prüfen:** Mitglieder der Ausbilder-Gruppe (inkl. dir selbst) dürfen
   in dieser Auswahl NICHT als aktivierbare Azubis auftauchen — das ist
   Absicht (verhindert, dass jemand sein eigenes Berichtsheft
   akzeptiert)
4. Prüfen: Existiert direkt nach der Aktivierung automatisch eine
   Deckblatt-PDF-Datei im Dateibereich des Azubis?


## Test 3 — Stammdaten eines Azubis bearbeiten / Status ändern

1. Bei einem bereits aktiven Azubi „Bearbeiten" nutzen und einen Wert
   ändern (z. B. Verantwortlichen wechseln) — prüfen ob die Änderung
   übernommen wird
2. „Beenden" bei einem Test-Azubi ausprobieren — prüfen, ob er danach
   aus der aktiven Wochenübersicht verschwindet
3. „Reaktivieren" ausprobieren — prüfen, ob er danach wieder normal
   nutzbar ist


## Test 4 — Eingereichte Woche prüfen

Warten (oder mit dem Azubi absprechen), bis eine Woche eingereicht
wurde.

1. Prüfen: Kommt eine Benachrichtigung an (In-App-Glocke und/oder
   E-Mail), sobald ein Azubi eine Woche einreicht?
2. Zur Prüfen-Ansicht wechseln — die eingereichte Woche sollte dort mit
   allen Tageseinträgen erscheinen (Fach, Stunden, Inhalt, Tätigkeit je
   Tag, nicht nur eine Zusammenfassung)
3. Prüfen: Sind alle Angaben des Azubis vollständig und korrekt lesbar?

**Fall A — Akzeptieren**
- Woche akzeptieren
- Prüfen: Bekommt der Azubi eine Benachrichtigung?

**Fall B — Zurückweisen**
- Woche mit einem Kommentar zurückweisen (z. B. „Bitte Dienstag noch
  ergänzen")
- Prüfen: Bekommt der Azubi den Kommentar per Benachrichtigung
  (In-App und E-Mail)?
- Prüfen: Kann der Azubi die Woche danach bearbeiten und erneut
  einreichen? Landet die erneute Einreichung wieder bei dir zur Prüfung?

Bitte **beide Fälle** einmal testen, idealerweise mit unterschiedlichen
Testwochen.


## Test 5 — Vertretungsfall (mehrere Ausbilder)

Falls mehr als ein Ausbilder in der Gruppe ist:

1. Mit einem zweiten Ausbilder-Account einloggen, der NICHT als
   „Verantwortlicher" für einen bestimmten Azubi eingetragen ist
2. Prüfen: Kann dieser zweite Ausbilder trotzdem die Wochen dieses
   Azubis sehen und prüfen (Krankheits-/Urlaubsvertretung)?
3. Prüfen: Bekommt weiterhin nur der eingetragene Verantwortliche die
   Einreichungs-Benachrichtigung, oder beide?


## Test 6 — Wöchentliche und monatliche Hintergrundfunktionen

Diese laufen im echten Betrieb automatisch (per Cronjob) zu festen
Terminen — für den Test kann der Systemverwalter sie über die
Kommandozeile sofort auslösen (siehe `docs/INSTALL-ADMIN-GUIDE.md`,
Abschnitt 11). Mit ihm absprechen, wenn du Folgendes testen willst:

- **Wöchentliche Erinnerung** an Azubis, die ihre Woche noch nicht
  eingereicht haben
- **Ausbilder-Digest**: Zusammenfassung offener/eingereichter Wochen zu
  deinem persönlich eingestellten Termin (Einstellungen → Persönlich →
  Berichtsheft) — prüfen, dass jeder Ausbilder nur zu seinem eigenen
  Termin einen Digest bekommt, nicht zu dem eines Kollegen
- **Lehrjahresabfrage**: jährliche Erinnerung, das Lehrjahr der Azubis
  zu prüfen/anzupassen


## Test 7 — Nach 4 akzeptierten Wochen: automatischer Export

Sobald 4 zusammenhängende Wochen eines Azubis akzeptiert sind, sollte
automatisch ein mehrseitiges PDF im Dateibereich des Azubis erscheinen.

- Prüfen: Ordner „Berichtsheft - <Nachname>, <Vorname>" vorhanden, PDF
  darin lesbar, Seiten in der richtigen Reihenfolge (keine leeren oder
  vertauschten Seiten)?
- Prüfen: Hast du als Ausbilder ebenfalls Zugriff auf diesen Ordner
  (Gruppenfreigabe)?


## Test 8 — IHK-Gesamtnachweis erzeugen

Diese Funktion kombiniert das Deckblatt und ALLE bisher akzeptierten
Wochen eines Azubis zu einem einzigen PDF (IHK-Vorgabe: max. 35 MB) —
gedacht für die Abgabe am Ende der Ausbildung oder zu einem
Ausbildungsabschnitt.

1. Bei einem aktiven Azubi mit mindestens 2–3 akzeptierten Wochen den
   Button „IHK-Gesamtnachweis erzeugen" anklicken
2. Prüfen: Wird eine Datei „<Nachname> <Vorname> - Gesamtnachweis.pdf"
   im selben Ordner erzeugt?
3. Prüfen: Ist die Seitenreihenfolge korrekt — Deckblatt zuerst, dann
   Ausbildungsnachweis Nr. 1, 2, 3 usw. in aufsteigender Reihenfolge
   (nicht nach Erfassungsdatum, sondern nach der aufgedruckten Nummer)?
4. Erneut erzeugen (z. B. nachdem eine weitere Woche akzeptiert wurde)
   — prüfen, ob die alte Datei korrekt durch die neue ersetzt wird


## Was am Ende dokumentiert werden sollte

- Welche Schritte oben sind fehlgeschlagen oder haben sich falsch
  verhalten (mit Screenshot, wenn möglich)?
- Welche Rückmeldungen kamen von den beteiligten Azubis (deren
  Testleitfaden, `docs/TESTLEITFADEN-AZUBI.md`)?
- Gibt es fachliche Wünsche/Lücken, die während des Tests aufgefallen
  sind (z. B. fehlende Felder, unklare IHK-Anforderungen)?

Bitte die gesammelten Rückmeldungen an den App-Verantwortlichen
weiterleiten.
