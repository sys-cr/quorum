# Quorum

**Sprache:** Deutsch · [English](README.en.md)

Stud.IP-6-Plugin: Audience Response System (Software-Clicker). Lehrende stellen in Sekunden eine Live-Abstimmung; Studierende antworten anonym per QR-Code vom Smartphone — ohne Stud.IP-Login.

## Dokumentation

- **[Installationsanleitung](docs/install/de/README.md)** — Einrichtung von null in einer Stud.IP-6-Instanz (inkl. Cliqr-Übernahme).
- **[Betrieb & Administration](docs/admin/de/README.md)** — Konfiguration, Wartung, Laufzeit-Topologie und Datenhaltung.
- **[Anwenderhandbuch](docs/user/de/README.md)** — Bedienung für Lehrende und Studierende.
- **[Entwicklerleitfaden](docs/developer/de/README.md)** — Architektur, Schichten, Verzeichnis-Layout, Build; Mitwirken über [`CONTRIBUTING.md`](CONTRIBUTING.md).

Englische Fassungen jeweils unter `docs/<bereich>/en/` (z. B. [`docs/install/en/`](docs/install/en/README.md)).

## Was Quorum bietet

- **Anonyme Polls per QR-Code** — Studierende scannen, antworten, fertig. Kein Stud.IP-Konto nötig. Der Doppel-Abstimmungs-Schutz wird rein im Browser gespeichert — kein IP-Tracking.
- **Fragetypen** — Multiple Choice (Einfach- und Mehrfachauswahl), Skala (Likert), Emoji-Reaktion, Freitext (Word Cloud) und Matrix; optional mit **Zeitlimit** (Countdown auf der Teilnehmer-Seite, automatischer Stopp).
- **Live-Sync für Teilnehmende** — die Vote-Seite wartet vor dem Start und wechselt von selbst zur Frage, sobald das Voting startet; bei Sammlungen folgt das Handy automatisch zur nächsten gestarteten Frage. Ein Scan genügt.
- **Sammlungen mit Ablaufsteuerung** — „Voting starten — alle Fragen" (Studierende klicken sich selbst durch) oder „Voting starten — Frage für Frage" (Lehrperson schaltet weiter); neue Umfragen direkt in der Sammlung anlegen; Archiv mit Reaktivieren und endgültigem Löschen.
- **Presenter** — Vollbild-Ansicht für den Beamer mit Tastatursteuerung (←/→ Frage wechseln, Leertaste Voting starten/beenden, N nächste Frage, L Leaderboard, F Vollbild, Q QR-Code, Esc beenden).
- **Quiz-Modus** — richtige Antworten markieren, Punkte berechnet der Server (richtig = Punkte, schneller = mehr Punkte bei Zeitlimit); pseudonymes Leaderboard mit strikt freiwilliger Teilnahme per frei gewähltem Spitznamen — live auf dem Handy und im Presenter.
- **Ergebnisse & Export** — Ergebnisseite je Umfrage (Option/Stimmen/Prozent, Freitext-Liste, Matrix-Tabelle), CSV- und PDF-Export für alle Fragetypen, re-importierbare JSON-Definition.
- **Freitext-Moderation** — Admin-Blocklist filtert vor dem Speichern; Lehrende können einzelne Antworten nachträglich entfernen.
- **Vergleichsrunden (Peer Instruction)** — dieselbe Frage in mehreren Runden stellen und mit Prozentpunkt-Deltas nebeneinander vergleichen; laufende Folgerunden blenden frühere Ergebnisse für Teilnehmende aus.
- **Demo-Inhalte** — über die Stud.IP-Hilfeleiste am Arbeitsplatz eine Beispiel-Sammlung mit Beispiel-Antworten laden.
- **Stud.IP-6-native** — nutzt die `ShortUrl`-API ab 6.2 (Polls-Kurzlinks erscheinen automatisch unter „Arbeitsplatz → Meine Kurzlinks"); abwärtskompatibel zu 6.0/6.1 über eigene Tabelle und automatischen Adapter.
- **Mobile-First** — vollständig nutzbar ab 375 px (typisches Smartphone-Hochformat).
- **Barrierefrei** — WCAG 2.2 AA; mit axe-core auf 0 Violations getestet.
- **Ohne externe Dienste** — keine Datenübertragung an Dritte; alle Assets self-hosted.

## Herkunft

Quorum knüpft an das Stud.IP-Plugin [„cliqr"](https://github.com/elan-ev/CliqrPlugin) des [elan e. V.](https://elan-ev.de) an und führt dessen Idee — ein Audience-Response-System direkt in Stud.IP — in modernisierter Form weiter. Vielen Dank für die Vorarbeit!

## Lizenz

GNU GPL-3.0-or-later **mit Zusatzbedingungen nach Abschnitt 7** — siehe [`LICENSE`](LICENSE) und [`SUPPLEMENTAL-TERMS.txt`](SUPPLEMENTAL-TERMS.txt).

Die Zusatzbedingungen verlangen, dass die grafische Oberfläche sichtbar Autor, Quellcode-Repository und Lizenz nennt. Quorum erfüllt das über die Stud.IP-Hilfeleiste (Helpbar) auf allen Plugin-Seiten.

## Autor

Entwickelt von **Bodo Steffen** — Quellcode: <https://github.com/sys-cr/quorum>
