# Quorum — Anwender-Dokumentation

Quorum ist ein Audience-Response-System (Software-Clicker) für Stud.IP 6: Lehrende starten Live-Abstimmungen, Studierende antworten anonym per QR-Code vom Smartphone — ganz ohne Stud.IP-Login.

Die vollständige Anleitung steht in **einer** Datei mit Inhaltsverzeichnis: **[anleitung.md](anleitung.md)**. Die Tabelle springt direkt zum passenden Abschnitt.

> **Dieselbe Anleitung steckt direkt in Quorum** — sie wird aus genau dieser `anleitung.md` gespeist, es gibt also keinen zweiten Pflegeort:
> - **Lehrende:** rechts in der **Hilfeleiste** auf jeder Quorum-Seite, mit **PDF-Download** der kompletten Anleitung.
> - **Studierende:** über **„Anleitung herunterladen"** im Fuß der Abstimmungs-Seite (Teilnehmenden-Teil als PDF).

## Wo möchten Sie etwas tun?

| Ich möchte … | Abschnitt |
|---|---|
| … in **meiner Veranstaltung** oder am Arbeitsplatz abstimmen lassen | [Wo Sie Quorum finden](anleitung.md#einstieg) |
| … Abstimmungen in **Sammlungen** organisieren | [Sammlungen](anleitung.md#sammlungen) |
| … im Hörsaal **präsentieren** (Vollbild, Tastatursteuerung, Frage für Frage) | [Presenter (Beamer)](anleitung.md#presenter) |
| … den **QR-Code** im Hörsaal auf den Beamer bringen | [QR-Code & Kurz-URL](anleitung.md#qr-code) |
| … wissen, was **Teilnehmende** auf dem Smartphone sehen | [Teilnehmen (für Studierende)](anleitung.md#teilnehmen) |
| … ein **Quiz** mit Punkten und Leaderboard durchführen | [Quiz-Modus](anleitung.md#quiz) |
| … **Ergebnisse** ansehen, Freitexte moderieren, als CSV/PDF exportieren | [Ergebnisse, Moderation & Export](anleitung.md#ergebnisse) |
| … Quorum **installieren, konfigurieren, migrieren** (Administration) | [Installation](../../install/de/README.md) · [Administration](../../admin/de/README.md) |

## In 30 Sekunden zur ersten Abstimmung

1. Öffnen Sie in Ihrer Veranstaltung den Reiter **„Quorum"**.
2. Legen Sie eine **Frage** mit Antwortoptionen an und klicken Sie **Abstimmung starten**.
3. Zeigen Sie den **QR-Code** (Vollbild für den Beamer). Studierende scannen, antworten anonym, fertig.
4. Verfolgen Sie die Stimmen **live** und klicken Sie **Abstimmung beenden**, wenn Sie genug Antworten haben.

## Gut zu wissen

- **Anonym & ohne Login:** Studierende brauchen kein Stud.IP-Konto, nur ein Gerät mit Kamera. Ein Doppel-Abstimmungs-Schutz wird rein im Browser gespeichert — kein IP-Tracking.
- **Live-Sync:** ein Scan genügt — die Teilnehmer-Seite wartet auf den Start und folgt bei Sammlungen automatisch zur nächsten Frage.
- **Mobil & barrierefrei:** alles funktioniert ab 375 px Bildschirmbreite, per Tastatur und Screenreader, im **High-Contrast-Mode von Stud.IP** (der OS-Dark-Mode wird bewusst nicht übernommen).
- **Mehrere Fragetypen:** Single Choice, Multiple Choice, Skala (numerisch 1–6 oder benannte Likert-Stufen), Emoji, Freitext, Matrix — mit **Vorlagen** für Skala und Emoji, dynamischen Optionen (2–20) und optionalem Zeitlimit.
- **Quiz & Richtig-Markierung:** richtige Antwort(en) optional bei Single/Multiple Choice markieren (Ergebnis-Hervorhebung); gewertetes Quiz mit Punkten und freiwilligem Leaderboard für Single Choice.
- **Peer Instruction:** dieselbe Frage in zwei Runden stellen und mit Delta-Tabelle vergleichen.
- **Ergebnisse & Export:** Ergebnisseite je Umfrage, CSV- und PDF-Export, re-importierbare JSON-Definition; Freitext-Moderation in zwei Stufen.
- **Demo-Inhalte:** über die Hilfeleiste am Arbeitsplatz lässt sich eine Beispiel-Sammlung mit Beispiel-Antworten laden.
- **Ohne externe Dienste:** keine Datenübertragung an Dritte; alles läuft auf Ihrem Stud.IP.

Die englische Fassung der Anwender-Doku liegt unter [`../en/`](../en/README.md).
