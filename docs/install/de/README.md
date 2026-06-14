# Quorum — Installation

Diese Anleitung richtet sich an die **Stud.IP-Administration** und beschreibt die **einmalige Einrichtung**: Plugin installieren, aktivieren und Daten aus dem Vorgänger-Plugin Cliqr übernehmen. Den laufenden Betrieb (Konfiguration, Datenschutz, Fehlerbehebung) behandelt die [Administration](../../admin/de/README.md).

> Architektur und Aufbau des Plugins beschreibt die [Entwickler-Dokumentation](../../developer/de/README.md).

## Inhalt

- [Voraussetzungen](#voraussetzungen)
- [Installation](#installation)
- [Webserver: Live-Ergebnisse (nur nginx)](#webserver-live-ergebnisse-nur-nginx)
- [Migration aus Cliqr](#migration-aus-cliqr)
- [Kurzlinks nach einem Stud.IP-Upgrade übertragen](#kurzlinks-nach-einem-studip-upgrade-übertragen)

## Voraussetzungen

| Anforderung | Wert |
|---|---|
| Stud.IP | 6.0 bis 6.x (Manifest: `studipMinVersion=6.0`) |
| PHP | ≥ 8.1 |
| PHP-Extension | `ext-gettext` |
| Datenbank | MariaDB/MySQL (wie von Stud.IP vorausgesetzt) |
| Webserver | Apache oder nginx — meist ohne Zusatzkonfiguration; bei nginx optional eine Regel für die Live-Ergebnisse (siehe unten) |

Quorum lädt **keine** externen Dienste (keine Google-/YOURLS-/Polr-Shortener, keine externen Schriften oder CDNs). Alle Assets werden vom eigenen Server ausgeliefert.

## Installation

### Variante A — Plugin-Paket über die Stud.IP-Oberfläche

1. Den Inhalt von `quorum-release/` als ZIP packen (Wurzel des ZIP = `plugin.manifest`).
2. In Stud.IP als Administration: **Admin → Plugins → Plugin hochladen** und die ZIP-Datei wählen.
3. Stud.IP entpackt das Plugin nach `public/plugins_packages/studip-quorum/QuorumStudipPlugin/`. Wichtig: Origin (`studip-quorum`) und Klassenname (`QuorumStudipPlugin`) müssen dem Pfad entsprechen.

### Variante B — manuell auf dem Server

1. `quorum-release/` nach `public/plugins_packages/studip-quorum/QuorumStudipPlugin/` kopieren.
2. PHP-Abhängigkeiten installieren: im Plugin-Verzeichnis `composer install --no-dev`.
3. Registrieren:
   ```bash
   php cli/studip plugin:register \
       public/plugins_packages/studip-quorum/QuorumStudipPlugin
   ```
   Erwartete Ausgabe: „The plugin was successfully registered."

> Stud.IP 6 nutzt Symfony Console — die CLI heißt `cli/studip` **ohne** `.php`-Suffix.

### Aktivieren

`plugin:register` legt nur den Datenbank-Eintrag an. Aktivieren Sie das Plugin anschließend in der **Plugin-Verwaltung** im Browser (oder per `php cli/studip plugin:enable …`). Beim Aktivieren legt Quorum seine Datenbanktabellen und die Konfiguration automatisch an.

### Verifikation

```sql
SELECT pluginname, pluginclassname, pluginpath
FROM plugins WHERE pluginclassname = 'QuorumStudipPlugin';
-- Quorum | QuorumStudipPlugin | studip-quorum/QuorumStudipPlugin
```

Danach erscheint in jeder Veranstaltung der Reiter **„Quorum"** und am Arbeitsplatz die Quorum-Kachel (abhängig von der Mindestrolle, siehe [Administration](../../admin/de/README.md)).

## Webserver: Live-Ergebnisse (nur nginx)

Die Live-Ergebnisse nutzen Server-Sent Events über eine langlebige HTTP-Verbindung. **In der Regel ist keine Webserver-Konfiguration nötig:** Quorum setzt im Antwort-Header `X-Accel-Buffering: no`, womit nginx das Output-Buffering für die Stream-Route von selbst aussetzt; unter Apache ist ohnehin nichts zu tun. Und greift der Push wider Erwarten nicht, fällt das Frontend automatisch auf ein 2-Sekunden-Polling zurück — die Ergebnisse aktualisieren sich weiterhin, nur eben gepollt statt gepusht.

Eine explizite nginx-Regel ist also **optional**. Sie lohnt nur, falls eine besonders restriktive Proxy-Konfiguration den `X-Accel-Buffering`-Header ignoriert und die Live-Ansicht spürbar im Polling-Modus hängt. Dann lässt sich das Buffering für die Stream-Route gezielt abschalten:

```nginx
location ~ ^/plugins\.php/quorumstudipplugin/api/stream/[^/]+$ {
    proxy_buffering off;
    proxy_read_timeout 3600s;
}
```

Effektiv nötig ist dabei nur `proxy_buffering off`. Der `proxy_read_timeout` ist reine Vorsorge: Quorum sendet alle 30 Sekunden einen Heartbeat, sodass die Verbindung auch unter dem nginx-Standard-Timeout nicht abreißt.

## Migration aus Cliqr

Quorum kann bestehende **Cliqr-Definitionen** übernehmen — einzelne Befragungen (Multiple Choice / Skala) **und** Sammlungen. Die historischen **Antworten werden bewusst nicht** übernommen (Stimmen abgelaufener Cliqr-Befragungen sind nicht mehr relevant); migrierte Umfragen kommen als nicht-laufende Vorlagen an. Die Original-Cliqr-Daten bleiben **unangetastet** — Cliqr und Quorum können danach nebeneinander laufen.

### Ausführen (Stud.IP 6.1+)

```bash
# Vorschau ohne Schreibzugriff:
php cli/studip quorum:migrate-cliqr --dry-run

# Echter Lauf:
php cli/studip quorum:migrate-cliqr
```

Auf **Stud.IP 6.0** (kein Plugin-CLI-Hook) gibt es einen Standalone-Wrapper:

```bash
php public/plugins_packages/studip-quorum/QuorumStudipPlugin/scripts/migrate-cliqr.php [--dry-run]
```

Eigenschaften: **idempotent** (ein zweiter Lauf schreibt nichts), **fehlertolerant** (defekte Datensätze werden im Bericht markiert, die Migration läuft weiter), **quell-schonend** (es wird nur aus den `etask_*`-Tabellen gelesen, nie hineingeschrieben). Eine Audit-Spur landet in `quorum_migration_log`.

Technische Details und Mapping-Tabelle: [Entwickler-Dokumentation](../../developer/de/README.md).

## Kurzlinks nach einem Stud.IP-Upgrade übertragen

Quorum erkennt die Stud.IP-Version automatisch und nutzt ab 6.2 die native `short_urls`-Tabelle, auf 6.0/6.1 eine eigene Tabelle. Nach einem Upgrade von 6.0/6.1 auf 6.2 können Sie die plugin-eigenen Kurzlinks in die native Verwaltung übertragen — dann erscheinen sie für die Lehrenden unter „Meine Kurzlinks":

```bash
php cli/studip quorum:migrate-short-urls --dry-run   # Vorschau
php cli/studip quorum:migrate-short-urls             # echter Lauf
```

Auch dieser Vorgang ist idempotent und alias-erhaltend — **bestehende QR-Codes funktionieren weiter**. Es besteht keine Eile: solange der Befehl nicht gelaufen ist, bleibt der Composite-Modus aktiv und alle Aliase sind auflösbar.
