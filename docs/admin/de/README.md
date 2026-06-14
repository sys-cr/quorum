# Quorum — Administration

Diese Anleitung richtet sich an die **Stud.IP-Administration** und beschreibt den **laufenden Betrieb**: Quorum konfigurieren, Datenschutz, Deaktivieren/Deinstallieren und Fehlerbehebung. Die einmalige Einrichtung (Installation, Aktivierung, Cliqr-Migration) behandelt die [Installation](../../install/de/README.md).

## Inhalt

- [Konfiguration: Mindestrolle (`QUORUM_MIN_ROLE`)](#konfiguration-mindestrolle-quorum_min_role)
- [Konfiguration: Freitext-Blocklist (`QUORUM_FREITEXT_BLOCKLIST`)](#konfiguration-freitext-blocklist-quorum_freitext_blocklist)
- [Datenschutz](#datenschutz)
- [Deaktivieren & Deinstallieren](#deaktivieren--deinstallieren)
- [Fehlerbehebung](#fehlerbehebung)

## Konfiguration: Mindestrolle (`QUORUM_MIN_ROLE`)

Steuert, **ab welcher Stud.IP-Systemrolle** die Arbeitsplatz-Kachel und die kursunabhängige Verwaltung sichtbar sind.

| | |
|---|---|
| Ort | **Konfiguration → Systemkonfiguration → Quorum → `QUORUM_MIN_ROLE`** |
| Standard | `dozent` |
| Erlaubte Werte | `user`, `autor`, `tutor`, `dozent`, `admin`, `root` |

Ungültige Werte (Tippfehler o. Ä.) fallen automatisch auf den Standard `dozent` zurück — eine Fehlkonfiguration kann die Verwaltung also nicht versehentlich für anonyme Nutzer:innen öffnen. Der Eintrag wird beim Aktivieren angelegt und beim Deaktivieren wieder entfernt; war das Plugin bereits vor diesem Stand aktiv, ergänzt ihn eine Migration nachträglich.

Unabhängig von dieser Einstellung gilt im Veranstaltungskontext immer das normale Stud.IP-Rechtemodell: der „Quorum"-Reiter erfordert mindestens **Tutor-Rechte** in der jeweiligen Veranstaltung.

## Konfiguration: Freitext-Blocklist (`QUORUM_FREITEXT_BLOCKLIST`)

Filtert anonyme **Freitext-Antworten vor dem Speichern**: enthält eine Antwort einen der hinterlegten Sperrbegriffe, wird sie gar nicht erst angenommen.

| | |
|---|---|
| Ort | **Konfiguration → Systemkonfiguration → Quorum → `QUORUM_FREITEXT_BLOCKLIST`** |
| Format | komma-getrennte Begriffe (z. B. `begriff1, begriff2`) |
| Standard | leer = Moderation aus |

Die Blocklist ist die erste von zwei Moderations-Stufen: Lehrende können zusätzlich einzelne bereits gespeicherte Freitext-Antworten auf der Ergebnisseite **nachträglich entfernen** (siehe [Ergebnisse, Moderation & Export](../../user/de/anleitung.md#ergebnisse)).

## Datenschutz

- Studierende nehmen **anonym** teil; für die Teilnahme ist kein Stud.IP-Login nötig.
- Der Schutz vor versehentlichem Doppel-Abstimmen wird **rein im Browser** (localStorage) gespeichert. Quorum betreibt **kein IP-Tracking** und legt je Antwort **kein** client- oder personenbezogenes Merkmal ab — gespeichert werden nur die Auswahl bzw. der Freitext und ein Zeitstempel.
- Es werden **keine** personenbezogenen Daten in Logs oder URLs abgelegt; CSV-Exporte enthalten nur aggregierte Zahlen bzw. anonyme Freitexte.

## Deaktivieren & Deinstallieren

Beim **Deaktivieren** entfernt Quorum seinen Konfigurationseintrag (`QUORUM_MIN_ROLE`). Die Datentabellen bleiben erhalten, damit beim erneuten Aktivieren nichts verloren geht. Eine vollständige Deinstallation (Tabellen entfernen) sollte bewusst und mit Backup erfolgen — die Quorum-Daten liegen in den Tabellen `quorum_polls`, `quorum_responses`, `quorum_poll_collections`, `quorum_short_urls` und `quorum_migration_log`.

## Fehlerbehebung

**„Plugin implementiert kein gültiges Interface" bei der Registrierung.** — Pfad und Manifest laufen auseinander. Das Plugin muss unter `…/studip-quorum/QuorumStudipPlugin/` liegen, passend zu `origin=studip-quorum` und `pluginclassname=QuorumStudipPlugin`.

**Die Live-Ergebnisse aktualisieren sich nur alle paar Sekunden statt sofort.** — Hinter nginx fehlt die Stream-Standortregel (siehe [Installation](../../install/de/README.md#webserver-live-ergebnisse-nur-nginx)). Funktional ist das unkritisch (Polling-Fallback), aber der Push ist dann inaktiv.

**Die Quorum-Kachel fehlt einzelnen Lehrenden am Arbeitsplatz.** — Deren Rolle erreicht die `QUORUM_MIN_ROLE` nicht. Wert prüfen oder Rolle anpassen.
