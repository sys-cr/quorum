# Quorum — Entwickler- & Architektur-Leitfaden

Quorum ist ein Audience-Response-/Live-Voting-Plugin für **Stud.IP 6**. Lehrende erstellen
Umfragen (Einfach-/Mehrfachauswahl, Skalen, Emoji-Stimmung, Freitext, Matrix), führen sie
live durch, und Studierende antworten anonym von jedem Gerät über einen Kurzlink oder
QR-Code. Ergebnisse werden in Echtzeit aggregiert und lassen sich als CSV oder PDF
exportieren.

Dieses Dokument ist der Einstiegspunkt für Entwickler, die am Plugin arbeiten. Es
beschreibt den Tech-Stack, die geschichtete Architektur, das Verzeichnis-Layout sowie
Build und Tests.

## Inhaltsverzeichnis

1. [Überblick](#überblick)
2. [Architektur & Schichten](#architektur--schichten)
3. [Verzeichnis-Layout](#verzeichnis-layout)
4. [Frontend](#frontend)
5. [Backend-Kernkonzepte](#backend-kernkonzepte)
6. [Internationalisierung](#internationalisierung)
7. [Build](#build)
8. [Tests](#tests)
9. [Datenbank](#datenbank)

## Überblick

| Bereich | Technologie |
|---|---|
| Host-Plattform | Stud.IP 6 (Trails MVC, SimpleORMap, Plugin-System) |
| Backend | PHP 8.1+, PSR-4 autoloaded unter dem Namespace `Quorum\` |
| Frontend | Vue 3, Pinia, Vue Router, vue-i18n |
| Bundler | Vite 6 |

Das Plugin registriert einen Kurs-Reiter („Quorum") innerhalb einer Stud.IP-Veranstaltung
und eine Kachel/Seite im persönlichen Arbeitsplatz, sodass Lehrende Umfragen entweder an
eine Veranstaltung gebunden oder unabhängig davon anlegen können.

## Architektur & Schichten

Das Backend folgt einer strikten, azyklischen Schichtung. Abhängigkeiten zeigen immer nur
nach unten:

```
Delivery   controllers/*.php        dünne Trails-Controller — Auth-Gates, Mount, JSON
   │
   ▼
Service    lib/**/*Service.php       PollsService, CollectionsService, ManualService, …
   │
   ▼
Domain     lib/Polls, lib/Quiz,      Value Objects & DTOs — Poll, Collection, PollType,
           lib/Url, lib/Export …     CompareChain, QuizScorer, Exporter
   │
   ▼
Infra      lib/Models + Repositories SimpleORMap-Models, Repositories mit Single-Query-
                                     Lookups (kein N+1)
```

Controller enthalten **keine Geschäftslogik**. Sie prüfen Berechtigungen, lesen
Request-Parameter, delegieren an einen Service und rendern eine View oder JSON. Die
gesamte Domänenlogik liegt im PSR-4-Namespace `Quorum\`, gemappt auf `lib/` (siehe
`composer.json`).

Controller sind nach Zielgruppe und Protokoll aufgeteilt:

- `index.php` — Kurs-Reiter, mountet die Course-App (erfordert Tutor-Rechte in der
  Veranstaltung).
- `workplace.php` — persönlicher Arbeitsplatz: CRUD für Umfragen und Sammlungen,
  Presenter-Start (erfordert die konfigurierte Mindestrolle plus Eigentümer-Prüfung).
- `api.php` — JSON-Endpunkte für die Lehrenden-Apps plus Live-Ergebnis-Snapshots.
- `p.php` / `u.php` — öffentliche, anonyme Routen für die Studierenden-Vote-Seite und den
  Kurzlink-Resolver.

## Verzeichnis-Layout

```
dev/
├── lib/                      PHP-Backend (Namespace Quorum\)
│   ├── Polls/                Kern-Domäne: Poll, PollType, Services, Repositories
│   ├── Quiz/                 Quiz-Scoring
│   ├── Export/               CSV- und PDF-Exporter
│   ├── Manual/               In-App-Anleitungs-Service
│   ├── Demo/                 Demo-Content-Seeder
│   ├── Migration/            Daten-Import-/Migrations-Helfer
│   ├── Url/                  Kurzlink-Handling
│   ├── Models/               SimpleORMap-Models
│   └── Vite/                 Manifest-Reader (Auflösung gehashter Assets)
├── controllers/             dünne Trails-Controller (index, workplace, api, p, u)
├── resources/vue/
│   ├── course-app/           Lehrenden-Ansicht im Kurs-Reiter
│   ├── workplace-app/        Übersicht im persönlichen Arbeitsplatz
│   ├── presenter-app/        Beamer-/Präsentations-Ansicht
│   ├── polls-app/            anonyme Studierenden-Vote-Seite
│   ├── components/           geteilte Komponenten
│   └── locales/              de.json / en.json (vue-i18n-Kataloge)
├── migrations/              nummerierte Schema-Migrationen (quorum_*-Tabellen)
├── locale/                  PHP-gettext-Kataloge (Domain „quorum")
└── docs/                    Dokumentation
```

## Frontend

Das Frontend besteht aus **vier unabhängigen Vue-3-Anwendungen**, jede mit eigenem
Vite-Entry und eigenem Output-Bundle:

| App | Zielgruppe | Gemountet von |
|---|---|---|
| `course-app` | Lehrende, im Kurs-Reiter | Course-Controller |
| `workplace-app` | Lehrende, persönlicher Arbeitsplatz | Workplace-Controller |
| `presenter-app` | Beamer / Live-Präsentation | Workplace-Controller |
| `polls-app` | Studierende, anonyme Vote-Seite | Public-Controller |

Jede App startet aus ihrer eigenen `main.js`, liest den Laufzeit-Kontext (Kurs-ID,
Plugin-URL, CSRF-Token, Sprache) aus den `data-*`-Attributen des Mount-Elements und mountet
auf einen einzelnen Wurzelknoten, der von einer bewusst minimalen PHP-View gerendert wird.

**Vue ist in jede App gebündelt.** Stud.IP 6 liefert Vue nur als UMD/IIFE-Global und liefert
Pinia, Vue Router oder vue-i18n überhaupt nicht mit, sodass sich der ESM-Stil
`import { … } from 'vue'` nicht vom Host bedienen lässt. Quorum bündelt daher den gesamten
Vue-Stack selbst — derselbe Ansatz wie bei Stud.IPs eigenen Apps. Jeder Entry erzeugt ein
präfigiertes, inhaltsgehashtes Bundle (z. B. `quorum-courseapp-<hash>.js`), das sich in
Netzwerk-Logs leicht erkennen lässt.

Der State liegt in Pinia-Stores; asynchrone Actions folgen einer `loading`/`error`-Flag-
Konvention. Die Course-App nutzt Vue Router mit Hash-History, weil Stud.IP nur die
Plugin-Index-Route ausliefert — In-App-Navigation ändert nur das URL-Fragment und löst
keinen Server-Roundtrip aus.

## Backend-Kernkonzepte

- **PollType** (`lib/Polls/PollType.php`) ist die alleinige Wahrheitsquelle für die
  Fragetypen (`mc`, `multi`, `scales`, `emoji`, `freitext`, `matrix`). Service-Validierung,
  Controller, Importer und der Datenbank-Default prüfen alle gegen dieselben Konstanten.
- **Anonymes Voting.** Die Studierenden-Seite und der Antwort-Submit-Endpunkt sind
  öffentlich und nur über ein Poll-Token identifiziert. Mutierende Lehrenden-Requests tragen
  das Stud.IP-CSRF-Token; der anonyme Vote-Endpunkt stützt sich stattdessen auf eine
  fail-closed Same-Origin-Prüfung, da anonyme Clients kein Session-CSRF-Token besitzen. Mit
  einer Antwort werden **keine** personenbeziehbaren Daten gespeichert — keine IP, kein
  Client-Hash; der Schutz vor versehentlichem Doppel-Abstimmen liegt rein im Browser des
  Teilnehmenden (localStorage).
- **Live-Ergebnis-Sync.** Während eine Umfrage läuft, holen Lehrenden- und Presenter-Ansicht
  aggregierte Zählungen, indem sie in kurzem Intervall einen JSON-Snapshot-Endpunkt pollen.
  Die Aggregation passiert in SQL und liefert nur Options-IDs und Zählungen — niemals
  Nutzer-IDs oder Antwort-Details.
- **Sammlungen** gruppieren Umfragen zu wiederverwendbaren Sets (Vorlagen), die angelegt,
  sortiert, archiviert und als Sequenz präsentiert werden können.
- **Quiz-Scoring** (`lib/Quiz/QuizScorer.php`) erlaubt das Markieren korrekter Optionen und
  das Bewerten von Antworten, genutzt vom Quiz-Modus.
- **Export.** Ergebnisse lassen sich als CSV (`ResultsCsvExporter`) je Umfrage oder je
  Sammlung exportieren sowie als PDF (`ResultsPdfExporter`). Auch eine Anleitung ist als PDF
  verfügbar.

## Internationalisierung

Zwei unabhängige Schichten, beide mit Deutsch und Englisch:

- **PHP-/UI-Strings** nutzen eine eigene gettext-Domain `quorum` über den Helfer
  `_quorum()` (`lib/i18n.php`). Quellsprache ist Deutsch; `_quorum()` schlägt einen String im
  Plugin-Katalog nach und **fällt auf die Stud.IP-Core-Domain (`_()`) zurück**, wenn er dort
  nicht gefunden wird, sodass generische Begriffe (Speichern, Abbrechen, …) ihre
  Core-Übersetzung erben und nicht doppelt geführt werden. Die Kataloge liegen unter
  `locale/<lang>/LC_MESSAGES/quorum.{po,mo}`. Der Helfer degradiert sauber, wenn
  `ext-gettext` nicht verfügbar ist (CLI/Tests).
- **Vue-Strings** nutzen **vue-i18n** im Composition-Modus, mit Katalogen in
  `resources/vue/locales/{de,en}.json`. vue-i18n wird (statt des gettext-Plugins des Hosts)
  verwendet, damit Komponenten-Tests in Node ohne Build-Zeit-Extraktionsschritt laufen und
  die Kataloge in diesem Repository versioniert bleiben. Schlüssel folgen einem
  hierarchischen Muster (z. B. `polls.loading`).

## Build

Das veröffentlichte Paket liefert die gebauten Frontend-Bundles bereits unter `public/` mit,
sodass ein einfacher Checkout nur die Laufzeit-Abhängigkeiten benötigt:

```bash
composer install --no-dev   # PHP-Autoloader + Laufzeit-Abhängigkeiten
npm install                 # JS-Toolchain (nur zum Neu-Bauen der Bundles)
npm run build               # vite build → public/ (+ public/.vite/manifest.json)
npm run dev                 # Watch-Modus (während der Entwicklung)
```

Vite schreibt inhaltsgehashte Bundles nach `public/` plus ein Manifest. Zur Request-Zeit
löst die PHP-View den Entry-Namen über den Manifest-Reader (`lib/Vite/Manifest.php`) zu
seiner gehashten URL auf — Cache-Busting ohne manuelle URL-Pflege.

## Tests

Das veröffentlichte Paket liefert nur Laufzeit-Code und die gebauten Bundles — es enthält
**keine** Test-Suite. Die folgenden Hinweise sind eine **Empfehlung** für alle, die das
Plugin weiterentwickeln oder erweitern, keine Beschreibung mitgelieferter Dateien.

Die geschichtete Architektur (siehe oben) macht den Code testbar: Services und
Domänen-Objekte haben keine Framework-Abhängigkeiten und lassen sich isoliert prüfen. Eine
sinnvolle Aufteilung der Abdeckung:

| Schicht / Belang | Werkzeug | Was abzudecken ist |
|---|---|---|
| Domäne & Services (PHP) | **PHPUnit** | `PollType`-Validierung, die `*Service`-Klassen, `QuizScorer`, CSV-/PDF-Exporter, der Cliqr-Migrator (Idempotenz, Fehlertoleranz). Reine Unit-Tests — keine Datenbank für die Domäne nötig. |
| Repositories & Migrationen (PHP) | **PHPUnit** (Integration) | Single-Query-Lookups gegen eine Test-Datenbank, Schema-Migrationen wenden sauber an/rollen sauber zurück. |
| Pinia-Stores & Composables (JS) | **Vitest** + Vue Test Utils | die `loading`/`error`-Action-Konvention, der localStorage-Doppelvote-Schutz im Polls-Store und der SSE→Polling-Fallback in `useLiveResults` (Watchdog, Backoff). |
| Vue-Komponenten (JS) | **Vitest** + Vue Test Utils | Rendering und Interaktion der vier Apps und der geteilten Komponenten. |
| Barrierefreiheit | **axe-core** | innerhalb der Komponenten- und E2E-Tests ausführen; Ziel WCAG 2.2 AA beim 375-px-Mobile-Breakpoint. |
| Kritische End-to-End-Flows | **Playwright** | anonymes Token-Voting, Presenter-Tastaturnavigation, Teilnehmer-Live-Sync. Auf einem Desktop- und einem Mobile-Viewport ausführen. |
| Statische Analyse & Stil | **PHPStan** (Level 5) und **PHP-CS-Fixer** (PSR-12) | Typfehler abfangen und vor dem Review einen einheitlichen Stil halten. |

Der anonyme Vote-Pfad und der Live-Ergebnis-Fallback sind die lohnendsten Ziele: beide sind
nutzerseitig sichtbar, beide haben nicht offensichtliche Fehlermodi und beide lassen sich
mit schnellen Unit-Tests gut abdecken.

## Datenbank

Alle Plugin-Tabellen tragen das Präfix `quorum_`. Das Schema wird durch nummerierte
Migrationen unter `migrations/` (`NN_*.php`) erzeugt und weiterentwickelt, ausgeführt über
das Migrations-Tooling des Plugins.

| Tabelle | Inhalt |
|---|---|
| `quorum_polls` | Umfragen: Token, optionale Kurs-Bindung, Eigentümer, Frage, Typ, Optionen (JSON), Lebenszyklus/Ablauf |
| `quorum_responses` | einzelne Antworten als JSON; keine personenbeziehbaren Daten |
| `quorum_poll_collections` | Sammlungen / Vorlagen (Name, Eigentümer, Reihenfolge) |
| `quorum_short_urls` | plugin-eigene Kurzlinks (Fallback für Hosts ohne native Kurz-URLs) |
| `quorum_migration_log` | Audit-Trail für idempotente Daten-Importe |

Repositories nutzen Single-Query-Lookups (z. B. das Aggregieren von Zählungen für viele
Umfragen in einer Anweisung), um N+1-Zugriffsmuster zu vermeiden.
