# Quorum — Anleitung

Quorum ist das Audience-Response-Plugin für Stud.IP: Lehrende stellen eine Live-Abstimmung, und Studierende antworten **anonym** — einfach den QR-Code scannen (oder die Kurz-URL eintippen), ohne Stud.IP-Login und ohne App-Installation. Die Ergebnisse erscheinen live auf dem Beamer.

Diese Anleitung führt von der Teilnahme über das Anlegen und Durchführen von Abstimmungen bis zu Presenter, Quiz, Ergebnis-Export und Konfiguration.

## Inhalt

- [Teilnehmen (für Studierende)](#teilnehmen)
- [Schnellstart](#schnellstart)
- [Wo Sie Quorum finden](#einstieg)
- [Abstimmungen anlegen](#anlegen)
- [Fragetypen](#fragetypen)
- [Eine Abstimmung durchführen](#durchfuehren)
- [QR-Code & Kurz-URL](#qr-code)
- [Presenter (Beamer)](#presenter)
- [Sammlungen](#sammlungen)
- [Peer Instruction (Runden vergleichen)](#peer-instruction)
- [Ergebnisse, Moderation & Export](#ergebnisse)
- [Quiz-Modus](#quiz)
- [Demo-Inhalte](#demo)
- [Konfiguration](#konfiguration)
- [Datenschutz](#datenschutz)
- [Häufige Fragen](#faq)

<!-- AUDIENCE:STUDENT:START -->
<a id="teilnehmen"></a>
## Teilnehmen (für Studierende)

Sie brauchen **keinen Stud.IP-Login** und **keine App** — ein Smartphone-Browser genügt. Scannen Sie den QR-Code (oder tippen Sie die Kurz-URL ein), und Sie landen auf der anonymen Vote-Seite.

### Die Seite aktualisiert sich von selbst

Die Vote-Seite ist **live synchronisiert** — einmal scannen genügt für die ganze Sitzung:

- **Vor dem Start** zeigt die Seite „Die Abstimmung hat noch nicht gestartet." Sobald die Lehrperson das Voting startet, wechselt die Seite **von selbst** zur Frage — kein Neuladen, kein neuer Scan.
- **Bei Sammlungen** folgt das Handy automatisch: startet die Lehrperson die nächste Frage, erscheint sie automatisch auf allen Geräten.

### Antworten

1. Frage lesen, Antwort(en) antippen.
2. **Absenden.** Die Seite bestätigt die Antwort.

Je nach Fragetyp tippen Sie eine Option an (Single Choice), mehrere (Mehrfachauswahl), einen Skalenwert, ein Emoji, geben einen kurzen Text ein (Freitext) oder bewerten mehrere Aussagen (Matrix).

### Zeitlimit & Countdown

Hat die Lehrperson ein **Zeitlimit** gesetzt, zeigt die Seite einen **Countdown**. Läuft die Zeit ab, stoppt die Abstimmung automatisch — danach sind keine Antworten mehr möglich.

### Schutz vor Doppel-Abstimmung

Wer bereits abgestimmt hat, sieht beim Neuladen der Seite die **Bestätigung** statt der Frage. Dieser Schutz wird **rein im Browser** gespeichert — Quorum betreibt **kein IP-Tracking** und legt keine personenbezogenen Daten ab.

### Quiz: freiwillig aufs Leaderboard

Läuft die Frage im [Quiz-Modus](#quiz), können Sie **freiwillig** an einer Bestenliste teilnehmen:

- Häkchen **„Mit Spitznamen am Leaderboard teilnehmen (freiwillig)"** setzen und einen **frei gewählten Spitznamen** eintragen — keinen echten Namen.
- Der Spitzname lässt sich **vor jeder Antwort ändern oder abwählen**.
- Nach dem Absenden zeigt das Handy das **Leaderboard live** an.

**Ohne Häkchen** zählt Ihre Antwort ganz normal für das Ergebnis — sie taucht aber **nirgends namentlich** auf.

### Der „Quorum"-Reiter in Ihrer Veranstaltung

Je nach Einstellung Ihrer Hochschule sehen Sie in der Veranstaltung einen **„Quorum"-Reiter**. Dort finden Sie:

- **Jetzt läuft:** die gerade laufenden Abstimmungen mit einem Knopf **„Jetzt teilnehmen"** — der öffnet dieselbe anonyme Vote-Seite wie der QR-Code (kein Login, kein Name).
- **Frühere Ergebnisse:** die Ergebnisse beendeter Abstimmungen, sofern die Lehrperson sie freigegeben hat.

Sehen Sie keinen Reiter, nehmen Sie wie gewohnt über **QR-Code oder Kurz-URL** teil — beides ist gleichwertig und ebenfalls anonym.
<!-- AUDIENCE:STUDENT:END -->

<a id="schnellstart"></a>
## Schnellstart

In rund 30 Sekunden zur ersten Abstimmung:

1. Öffnen Sie in Ihrer Veranstaltung den Reiter **„Quorum"** (oder die Quorum-Kachel am Arbeitsplatz).
2. **Abstimmung anlegen** → Frage eintippen, Fragetyp wählen, bei Auswahlfragen mindestens zwei Optionen eintragen, **Speichern**.
3. Abstimmung öffnen und **Abstimmung starten**.
4. **QR-Code zeigen** und auf den Beamer bringen — Studierende scannen und antworten anonym.
5. Die Stimmen erscheinen live; mit **Abstimmung beenden** frieren Sie das Ergebnis ein.

Mehr Details in den folgenden Abschnitten.

<a id="einstieg"></a>
## Wo Sie Quorum finden

Quorum hat zwei Einstiege:

- **In der Veranstaltung:** Klicken Sie im Veranstaltungs-Header den Reiter **„Quorum"**. Hier verwalten Sie die Abstimmungen dieses Kurses — ohne zusätzliche Anmeldung und ohne externe Links.
- **Am Arbeitsplatz (kursunabhängig):** Öffnen Sie **Mein Arbeitsplatz** und klicken Sie die Kachel **Quorum** („Live-Abstimmungen anlegen und auswerten"). Dort verwalten Sie Abstimmungen, die zu **keiner** einzelnen Veranstaltung gehören müssen — etwa für eine Gastvorlesung, ein kursübergreifendes Tutorium oder als wiederverwendbare Vorlagen.

In der Übersicht erscheint jede Abstimmung als Karte mit Frage, **Status-Abzeichen** („läuft" / „beendet", zusätzlich mit Text und Icon, also auch ohne Farbsehen erkennbar), **Stimmenzahl** und entweder dem **Veranstaltungs-Namen** oder dem Marker **„kursunabhängig"**.

**Rollen:** Im Veranstaltungskontext gilt das normale Stud.IP-Rechtemodell — der „Quorum"-Reiter erfordert mindestens **Tutor-Rechte** in der jeweiligen Veranstaltung. Die Arbeitsplatz-Kachel ist erst ab einer von der Administration konfigurierten **Mindestrolle** sichtbar (Standard: **Dozent:in**). Erscheint die Kachel nicht, erreicht Ihre Rolle vermutlich nicht diese Mindestrolle — siehe [Konfiguration](#konfiguration).

Beide Oberflächen funktionieren auf jedem Gerät ab 375 Pixel Breite (am Smartphone werden Tabellen zu gestapelten Karten), übernehmen **Dark Mode** und **High-Contrast-Mode** des Betriebssystems und sind vollständig per Tastatur und Screenreader bedienbar.

<a id="anlegen"></a>
## Abstimmungen anlegen

1. Klicken Sie **Abstimmung anlegen**.
2. Tragen Sie die **Frage** ein (reiner Text; Zeilenumbrüche bleiben erhalten).
3. Wählen Sie den **Fragetyp** (siehe [Fragetypen](#fragetypen)).
4. Bei auswahlbasierten Fragetypen: tragen Sie mindestens **zwei Antwortoptionen** ein.
5. Optional: setzen Sie ein **Zeitlimit in Minuten** — Teilnehmende sehen dann einen Countdown, und die Abstimmung stoppt automatisch, wenn die Zeit abläuft.
6. Optional (nur Single Choice): kreuzen Sie bei der korrekten Option **„Richtige Antwort (Quiz)"** an und aktivieren Sie **„Quiz-Modus aktivieren"** (siehe [Quiz-Modus](#quiz)).
7. Optional: binden Sie die Abstimmung an eine **Veranstaltung**. Lassen Sie das Feld leer, bleibt sie kursunabhängig (nachträgliches Binden geht jederzeit über **Bearbeiten**).
8. **Speichern.** Die Abstimmung erscheint in der Übersicht — zunächst nicht laufend, als Vorlage.

Eine fehlende Frage oder weniger als zwei Optionen weist Quorum serverseitig ab; Sie bekommen das Formular mit Ihren Eingaben und einer Fehlermeldung zurück. Der ausführliche **Matrix**-Typ wird über den Kurs-Reiter angelegt.

<a id="fragetypen"></a>
## Fragetypen

Welcher Typ passt, hängt davon ab, was Sie wissen wollen:

| Typ | Wofür |
|---|---|
| **Single Choice** | eine richtige bzw. bevorzugte Antwort aus mehreren Optionen |
| **Mehrfachauswahl** | mehrere Antworten gleichzeitig wählbar |
| **Skala / Likert** | Zustimmung oder Einschätzung auf einer Skala (z. B. 1–5) |
| **Emoji-Stimmung** | schnelles Stimmungsbild über Emojis |
| **Freitext** | offene Antworten in eigenen Worten (anonym) |
| **Matrix** | mehrere Aussagen gemeinsam auf einer Skala bewerten |

Alle Texte (Frage und Optionen) sind reiner Text; Zeilenumbrüche bleiben erhalten. Auswahlbasierte Fragen brauchen mindestens zwei Optionen.

<a id="durchfuehren"></a>
## Eine Abstimmung durchführen

1. Öffnen Sie die Abstimmung und klicken Sie **Abstimmung starten**.
2. Zeigen Sie den Studierenden die **Kurz-URL** (im Header sichtbar) oder den **QR-Code** (Knopf **„QR-Code zeigen"**, Vollbild für den Beamer — siehe [QR-Code & Kurz-URL](#qr-code)). Studierende scannen und antworten anonym, ganz ohne Stud.IP-Login.
3. Die Stimmen erscheinen **live** — die Anzeige aktualisiert sich innerhalb von rund zwei Sekunden nach jeder neuen Antwort.
4. Klicken Sie **Abstimmung beenden**, wenn genug Antworten da sind. Beendete Abstimmungen werden eingefroren.

Auch die **Teilnehmer-Seite ist live synchronisiert**: Wer den QR-Code schon vor dem Start scannt, sieht „Die Abstimmung hat noch nicht gestartet." und bekommt die Frage automatisch, sobald Sie starten — niemand muss neu laden oder neu scannen (siehe [Teilnehmen](#teilnehmen)).

Für die große Live-Darstellung im Hörsaal nutzen Sie am besten den [Presenter](#presenter).

<a id="qr-code"></a>
## QR-Code & Kurz-URL

Den QR-Code einer laufenden Abstimmung können Sie groß anzeigen und auf den Beamer projizieren — so scannen Studierende auch aus dem hinteren Hörsaal.

1. Starten Sie eine Abstimmung.
2. Klicken Sie **QR-Code zeigen** — der Dialog öffnet sich mit einem großen QR-Code.
3. Projizieren Sie das Fenster auf dem Beamer oder teilen Sie Ihren Bildschirm.

Im [Presenter](#presenter) erreichen Sie die große QR-Code-Anzeige zusätzlich über die Taste **Q**.

| Funktion | Beschreibung |
|---|---|
| **Vollbild** | Maximiert den QR-Code auf den gesamten Bildschirm — ideal für Beamer-Sharing |
| **SVG herunterladen** | Speichert den QR-Code als skalierbare Vektorgrafik (für Druckmaterialien) |
| **PNG herunterladen** | Speichert den QR-Code als Rasterbild (für Präsentationen) |
| **Schließen / Esc** | Schließt den Dialog |

**Tastatur:** **Tab** navigiert zwischen allen Schaltflächen, **Esc** schließt den Dialog; danach kehrt der Fokus automatisch zum auslösenden Button zurück.

**Kurz-URL:** Ist ein URL-Shortener in Stud.IP konfiguriert, zeigt der Dialog die **Kurz-URL** neben dem QR-Code an — das erleichtert das manuelle Eintippen für Studierende ohne Kamera.

<a id="presenter"></a>
## Presenter (Beamer)

Der Presenter ist die aufgeräumte Vollbildansicht für den Beamer: Frage, QR-Code und Live-Ergebnisse groß auf einer Seite — ohne Bearbeitungsmasken, ohne Seitenleiste. Alle Bedienelemente sind Standard-Buttons und zusätzlich per Tastatur erreichbar.

**Öffnen:**

- **Einzelne Abstimmung präsentieren** — über die jeweilige Umfrage-Karte bzw. Detailansicht.
- **Sammlung präsentieren** — führt durch die Abstimmungen einer [Sammlung](#sammlungen) der Reihe nach; mit ←/→ wechseln Sie zwischen den Fragen.

**Tastenkürzel:**

| Taste | Aktion |
|---|---|
| **←** / **→** | vorherige / nächste Frage anzeigen |
| **Leertaste** | Voting der aktuellen Frage starten / beenden |
| **N** | nächste Frage starten (Ablaufsteuerung „Frage für Frage") |
| **L** | Leaderboard ein-/ausblenden (nur [Quiz-Modus](#quiz)) |
| **F** | Vollbild ein/aus |
| **Q** | QR-Code groß anzeigen |
| **Esc** | Presenter beenden |

Die Tasten sind eine Abkürzung, keine Voraussetzung — jede Aktion gibt es auch als Button.

**Ablauf „Frage für Frage":** Haben Sie eine Sammlung mit **„Voting starten — Frage für Frage"** gestartet, schalten Sie im Presenter mit **„Nächste Frage starten"** (Button oder Taste **N**) weiter. Die Smartphones der Teilnehmenden folgen automatisch zur jeweils gestarteten Frage — niemand muss neu scannen.

**Beamer-Tipps:**

- **Taste F** bringt den Presenter in den Browser-Vollbildmodus — ideal direkt nach dem Umschalten auf den Beamer.
- **Taste Q** zeigt den QR-Code bildschirmfüllend, solange Teilnehmende noch beitreten; ein weiterer Druck kehrt zur Frage zurück.
- Die Live-Ergebnisse aktualisieren sich automatisch, während die Abstimmung läuft.

<a id="sammlungen"></a>
## Sammlungen

Sammlungen bündeln mehrere Abstimmungen, etwa als wiederverwendbare Fragensätze für eine Vorlesung. Sie erreichen sie über die **Sidebar-Ansicht „Sammlungen"** — sowohl am Arbeitsplatz als auch im **„Quorum"-Reiter der Veranstaltung**.

- **Anlegen / Bearbeiten:** über **Sammlungen → Neue Sammlung anlegen**. Name und Beschreibung sind reiner Text. Wie bei einzelnen Abstimmungen lässt sich eine Sammlung optional an eine **Veranstaltung** binden (im Kurs-Reiter automatisch, am Arbeitsplatz über das Veranstaltungs-Feld). Eine kursgebundene Sammlung erscheint im Kurs-Reiter — für Lehrende zur Verwaltung, für Studierende zum Mitmachen an laufenden Fragen und zur Einsicht freigegebener Ergebnisse. Die Kursbindung der Sammlung ist **unabhängig** von der Zuordnung ihrer einzelnen Fragen.
- **Mitsteuern (Co-Teaching):** Alle Lehrenden (Tutor:in/Dozent:in) einer Veranstaltung dürfen deren Abstimmungen und Sammlungen steuern — nicht nur, wer sie angelegt hat. Kursunabhängige Inhalte bleiben der anlegenden Person vorbehalten.
- **Abstimmungen zuordnen:** auf einer Abstimmungs-Karte die Aktion **„Zu Sammlung hinzufügen …"** wählen — oder direkt auf der Detailseite der Sammlung **„Neue Umfrage anlegen"** klicken; die neue Umfrage landet sofort in der Sammlung.
- **Reihenfolge ändern:** die Abstimmungen innerhalb einer Sammlung neu ordnen.
- **Archivieren / Wiederherstellen:** nicht benötigte Sammlungen archivieren; sie liegen dann in der Sidebar-Ansicht **„Archiv"** und lassen sich dort reaktivieren oder endgültig löschen.

**Eine Sammlung durchführen — zwei Start-Varianten:**

| Variante | Was passiert |
|---|---|
| **Voting starten — alle Fragen** | Alle Fragen sind sofort offen; Studierende klicken sich **selbst** der Reihe nach durch — etwa für Feedback-Bögen am Ende einer Sitzung. |
| **Voting starten — Frage für Frage** | Nur die erste Frage ist offen; **Sie** schalten im [Presenter](#presenter) mit **„Nächste Frage starten"** (oder Taste **N**) weiter — der klassische Clicker-Ablauf in der Vorlesung. |

**„Voting beenden"** stoppt alle laufenden Fragen der Sammlung auf einmal. Die Smartphones der Teilnehmenden folgen in beiden Varianten automatisch — niemand muss neu scannen.

<a id="peer-instruction"></a>
## Peer Instruction (Runden vergleichen)

Eine bewährte Lehrmethode: dieselbe Frage **vor und nach** einer Diskussionsrunde stellen und die Antworten vergleichen.

1. Führen Sie die erste Runde durch und beenden Sie sie.
2. Wählen Sie im Aktionsmenü **„Neue Runde …"** und dann **„Vergleichen"** — Quorum legt eine zweite Runde mit identischer Frage und identischen Optionen an. (Nicht zu verwechseln mit **„Voting wieder starten"**, das dieselbe Umfrage erneut aktiviert.)
3. Lassen Sie die Studierenden in Kleingruppen diskutieren.
4. Starten und beenden Sie die zweite Runde.
5. Klicken Sie **„Vergleich anzeigen"** — Quorum öffnet beide Runden nebeneinander mit einer **Delta-Tabelle**: welche Antwort hat um wie viele Prozentpunkte zugelegt oder verloren? Die Richtung wird durch Pfeil-Icon und Zahl gezeigt, nicht nur durch Farbe.

Der Vergleich funktioniert nur zwischen Runden zur **gleichen Frage**. Laufende Abstimmungen lassen sich nicht vergleichen — beenden Sie sie zuerst. Beliebig viele Folge-Runden sind möglich; typisch sind ein bis zwei. Während eine spätere Runde läuft, blendet Quorum die Ergebnisse der früheren Runde für die Studierenden aus (**„Blind-Modus"**), damit sie sich nicht beeinflussen lassen.

<a id="ergebnisse"></a>
## Ergebnisse, Moderation & Export

Jede Umfrage-Karte — auch im **Archiv** — trägt die Aktion **„Ergebnisse …"**. Sie öffnet eine schlichte, druckfreundliche Ergebnisseite ohne Bearbeitungselemente. (Für die große Live-Darstellung während des Vortrags nutzen Sie besser den [Presenter](#presenter).)

| Fragetyp | Darstellung |
|---|---|
| Single Choice, Mehrfachauswahl, Skala, Emoji | Tabelle mit **Option / Stimmen / Prozent** |
| Freitext | Liste der einzelnen (anonymen) Antworten |
| Matrix | Tabelle mit der Verteilung je Zeile |

**Downloads:**

| Download | Inhalt | Wo |
|---|---|---|
| **CSV-Export** | aggregierte Zahlen je Option bzw. anonyme Freitexte — für alle Fragetypen | Ergebnisseite |
| **PDF-Export** | dieselben Ergebnisse als Stud.IP-PDF, z. B. zum Ablegen im Kurs | Ergebnisseite |
| **Definition herunterladen** (JSON) | die Frage-Definition **ohne** Antworten — re-importierbar, z. B. zum Weitergeben an Kolleg:innen | Karten-Menü der Umfrage |

Kein Export enthält personenbezogene Daten der Studierenden.

**Freitext-Moderation** in zwei Stufen:

1. **Admin-Blocklist (vor dem Speichern):** Die Stud.IP-Administration kann unter `QUORUM_FREITEXT_BLOCKLIST` Sperrbegriffe hinterlegen; Antworten mit diesen Begriffen werden gar nicht erst gespeichert (siehe [Konfiguration](#konfiguration)).
2. **Nachträgliches Entfernen (durch Sie):** Auf der Ergebnisseite trägt jede Freitext-Antwort die Aktion **„Entfernen"**. Nach einer Bestätigung verschwindet die Antwort aus Ergebnis und Exporten — das Entfernen ist endgültig.

So bleibt die Hürde für spontane Antworten niedrig, ohne dass einzelne Störbeiträge die Beamer-Anzeige ruinieren.

### Ergebnisse für Studierende freigeben (Standard) oder verbergen

Studierende sehen die Ergebnisse beendeter Abstimmungen im **Quorum-Reiter** der Veranstaltung — das ist der **Standard**. Möchten Sie eine einzelne Abstimmung davon ausnehmen (z. B. eine heikle Frage), entfernen Sie beim **Anlegen** oder **Bearbeiten** das Häkchen **„Ergebnisse für Studierende im Kurs-Reiter sichtbar"**. Das lässt sich auch **nachträglich** noch ändern. Die Teilnahme an laufenden Abstimmungen bleibt davon unberührt — sie ist immer möglich. Freitext-Antworten, die Sie entfernt haben, erscheinen auch in der Studierenden-Ansicht nicht.

<a id="quiz"></a>
## Quiz-Modus

Der Quiz-Modus macht aus einer Single-Choice-Frage ein kleines Wettbewerbs-Quiz: richtige Antworten bringen Punkte, schnelle Antworten mehr Punkte, und wer mag, erscheint mit einem selbst gewählten Spitznamen auf dem Leaderboard.

**Anlegen:**

1. Legen Sie eine **Single-Choice-Frage** an (Arbeitsplatz oder Kurs-Reiter).
2. Kreuzen Sie bei der korrekten Option **„Richtige Antwort (Quiz)"** an.
3. Aktivieren Sie die Checkbox **„Quiz-Modus aktivieren"**.
4. Optional: setzen Sie ein **Zeitlimit in Minuten** — erst damit fließt die Antwortgeschwindigkeit in die Punkte ein.
5. Speichern und das Voting wie gewohnt starten.

**Punkte** berechnet der **Server** — Manipulation am Endgerät ist nicht möglich:

| Situation | Punkte |
|---|---|
| Richtige Antwort, **mit** Zeitlimit | Punkte für richtig, **schneller = mehr Punkte** |
| Richtige Antwort, **ohne** Zeitlimit | volle Punktzahl |
| Falsche Antwort | keine Punkte |

**Leaderboard:**

- **Auf dem Handy:** Teilnehmende sehen das Leaderboard **live nach dem Absenden** ihrer Antwort.
- **Im Presenter:** Taste **L** (oder der Leaderboard-Button) blendet die Bestenliste für den Beamer ein und aus.

**Freiwilligkeit & Datenschutz:** Teilnehmende erscheinen **nur auf eigenen Wunsch** auf dem Leaderboard. Sie setzen auf der Vote-Seite das Häkchen **„Mit Spitznamen am Leaderboard teilnehmen (freiwillig)"** und wählen einen **Spitznamen** — ausdrücklich **kein echter Name**, vor jeder Antwort änderbar oder abwählbar. **Ohne Häkchen** zählt die Antwort ganz normal für das Ergebnis, taucht aber nirgends namentlich auf. Es werden keine Klarnamen, Accounts oder IP-Adressen mit Antworten verknüpft.

<a id="demo"></a>
## Demo-Inhalte

In der Stud.IP-**Hilfeleiste** (Helpbar) der Arbeitsplatz-Seite finden Sie **„Demo-Inhalte laden"**: Quorum legt eine Beispiel-Sammlung mit Beispiel-Umfragen samt Beispiel-Antworten an — ideal, um Ergebnisseite, Presenter und Export gefahrlos auszuprobieren. Die Demo-Inhalte landen in Ihrem **Archiv** und lassen sich dort jederzeit wieder löschen; ein zweiter Klick legt keine Duplikate an.

<a id="konfiguration"></a>
## Konfiguration

Diese Einstellungen liegen unter **Konfiguration → Systemkonfiguration → Quorum** und werden von der Stud.IP-Administration verwaltet:

- **Mindestrolle `QUORUM_MIN_ROLE`** — ab welcher Stud.IP-Systemrolle die Arbeitsplatz-Kachel und die kursunabhängige Verwaltung sichtbar sind. Standard: `dozent`; erlaubte Werte: `user`, `autor`, `tutor`, `dozent`, `admin`, `root`. (Im Veranstaltungskontext gilt unabhängig davon immer das normale Stud.IP-Rechtemodell mit mindestens Tutor-Rechten.)
- **Freitext-Blocklist `QUORUM_FREITEXT_BLOCKLIST`** — komma-getrennte Sperrbegriffe; anonyme Freitext-Antworten mit einem dieser Begriffe werden vor dem Speichern abgewiesen. Standard: leer (Moderation aus). Siehe auch [Ergebnisse, Moderation & Export](#ergebnisse).
- **Kurzlinks** — ab Stud.IP 6.2 legt Quorum die Kurzlinks in der nativen Stud.IP-Kurzlink-Verwaltung ab; Sie finden sie dann unter **Arbeitsplatz → Meine Kurzlinks**. Auf 6.0/6.1 verwaltet Quorum sie intern; nach einem Upgrade können sie übernommen werden.

Einrichtungs- und CLI-Details: siehe [Installation](../../install/de/README.md) und [Administration](../../admin/de/README.md).

<a id="datenschutz"></a>
## Datenschutz

- Studierende nehmen **anonym** teil; für die Teilnahme ist **kein Stud.IP-Login** nötig.
- Quorum betreibt **kein IP-Tracking** und legt keine personenbezogenen Daten in Logs oder URLs ab. Der Schutz vor Mehrfachabstimmung wird **rein im Browser** gespeichert; serverseitig wird je Antwort kein client- oder personenbezogenes Merkmal abgelegt.
- Es werden **keine personenbezogenen Daten exportiert**; CSV- und PDF-Exporte enthalten nur aggregierte Zahlen bzw. anonyme Freitexte.
- Die Teilnahme am **Quiz-Leaderboard ist freiwillig** und erfolgt nur mit einem frei gewählten Spitznamen, nicht mit Klarnamen oder Account.

<a id="faq"></a>
## Häufige Fragen

**Muss ich als Studierende:r eine App installieren oder mich einloggen?** — Nein, ein Browser genügt, und die Teilnahme ist anonym und ohne Login.

**Kann die Lehrperson sehen, wie ich abgestimmt habe?** — Nein. Antworten werden anonym gespeichert; nur die aggregierten Zahlen (und anonyme Freitexte) sind sichtbar.

**Die Vote-Seite zeigt „Die Abstimmung hat noch nicht gestartet."** — Alles richtig gemacht: einfach warten, die Frage erscheint automatisch, sobald die Lehrperson startet.

**Die Quorum-Kachel fehlt am Arbeitsplatz.** — Ihre Rolle erreicht vermutlich nicht die konfigurierte Mindestrolle (`QUORUM_MIN_ROLE`). Wenden Sie sich an die Stud.IP-Administration.

**Kann ich eine kursunabhängige Abstimmung nachträglich an einen Kurs binden?** — Ja, über **Bearbeiten** der Abstimmung.

**Sehen andere Lehrende meine Abstimmungen?** — Nein. Die Übersicht zeigt ausschließlich Ihre eigenen Abstimmungen; der Zugriff ist serverseitig auf Sie als Eigentümer:in beschränkt.

**Kann ich Ergebnisse archivierter Umfragen noch exportieren?** — Ja, „Ergebnisse …" inklusive CSV/PDF funktioniert auch im Archiv.

**Lässt sich eine entfernte Freitext-Antwort wiederherstellen?** — Nein, das Entfernen ist endgültig — deshalb die Sicherheitsabfrage.

**Was ist der Unterschied zwischen CSV-Export und „Definition herunterladen"?** — Der CSV-Export enthält die **Antworten** (aggregiert bzw. anonyme Texte). Die JSON-Definition enthält nur die **Frage samt Optionen** und lässt sich wieder importieren.

**Kann ich mehrere Optionen als richtig markieren?** — Der Quiz-Modus ist für Single-Choice-Fragen mit eindeutig richtiger Antwort gedacht; markieren Sie mindestens eine Option als richtig.

**Zählt eine Quiz-Frage auch ohne Zeitlimit?** — Ja. Ohne Zeitlimit gibt es für jede richtige Antwort die volle Punktzahl, unabhängig vom Tempo.
