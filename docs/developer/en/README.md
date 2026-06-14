# Quorum — Developer & Architecture Guide

Quorum is an audience-response / live-voting plugin for **Stud.IP 6**. Lecturers create
polls (single/multiple choice, scales, emoji mood, free text, matrix), run them live, and
students answer anonymously from any device via a short link or QR code. Results aggregate
in real time and can be exported as CSV or PDF.

This document is the entry point for developers working on the plugin. It describes the
tech stack, the layered architecture, the directory layout, and how to build and test.

## Table of contents

1. [Overview](#overview)
2. [Architecture & layers](#architecture--layers)
3. [Directory layout](#directory-layout)
4. [Frontend](#frontend)
5. [Backend key concepts](#backend-key-concepts)
6. [Internationalisation](#internationalisation)
7. [Build](#build)
8. [Testing](#testing)
9. [Database](#database)

## Overview

| Area | Technology |
|---|---|
| Host platform | Stud.IP 6 (Trails MVC, SimpleORMap, plugin system) |
| Backend | PHP 8.1+, PSR-4 autoloaded under the `Quorum\` namespace |
| Frontend | Vue 3, Pinia, Vue Router, vue-i18n |
| Bundler | Vite 6 |

The plugin registers a course tab ("Quorum") inside a Stud.IP course and a tile/page in
the personal workspace, so lecturers can create polls either bound to a course or
independently of one.

## Architecture & layers

The backend follows a strict, acyclic layering. Dependencies only ever point downward:

```
Delivery   controllers/*.php        thin Trails controllers — auth gates, mount, JSON
   │
   ▼
Service    lib/**/*Service.php       PollsService, CollectionsService, ManualService, …
   │
   ▼
Domain     lib/Polls, lib/Quiz,      value objects & DTOs — Poll, Collection, PollType,
           lib/Url, lib/Export …     CompareChain, QuizScorer, exporters
   │
   ▼
Infra      lib/Models + Repositories SimpleORMap models, repositories with single-query
                                     lookups (no N+1)
```

Controllers contain **no business logic**. They verify permissions, read request
parameters, delegate to a service, and render a view or JSON. All domain logic lives in
the PSR-4 namespace `Quorum\` mapped to `lib/` (see `composer.json`).

Controllers split by audience and protocol:

- `index.php` — course tab, mounts the course app (requires tutor rights in the course).
- `workplace.php` — personal workspace: poll and collection CRUD, presenter launch
  (requires the configured minimum role plus an owner check).
- `api.php` — JSON endpoints for the lecturer apps plus live-result snapshots.
- `p.php` / `u.php` — public, anonymous routes for the student voting page and the
  short-link resolver.

## Directory layout

```
dev/
├── lib/                      PHP backend (namespace Quorum\)
│   ├── Polls/                core domain: Poll, PollType, services, repositories
│   ├── Quiz/                 quiz scoring
│   ├── Export/               CSV and PDF exporters
│   ├── Manual/               in-app manual service
│   ├── Demo/                 demo content seeder
│   ├── Migration/            data import/migration helpers
│   ├── Url/                  short-link handling
│   ├── Models/               SimpleORMap models
│   └── Vite/                 manifest reader (hashed asset resolution)
├── controllers/             thin Trails controllers (index, workplace, api, p, u)
├── resources/vue/
│   ├── course-app/           lecturer view inside the course tab
│   ├── workplace-app/        personal-workspace overview
│   ├── presenter-app/        projector / presentation view
│   ├── polls-app/            anonymous student voting page
│   ├── components/           shared components
│   └── locales/              de.json / en.json (vue-i18n catalogues)
├── migrations/              numbered schema migrations (quorum_* tables)
├── locale/                  PHP gettext catalogues (domain "quorum")
└── docs/                    documentation
```

## Frontend

The frontend is **four independent Vue 3 applications**, each with its own Vite entry and
output bundle:

| App | Audience | Mounted by |
|---|---|---|
| `course-app` | lecturer, inside the course tab | course controller |
| `workplace-app` | lecturer, personal workspace | workplace controller |
| `presenter-app` | projector / live presentation | workplace controller |
| `polls-app` | students, anonymous voting page | public controller |

Each app boots from its own `main.js`, reads runtime context (course id, plugin URL, CSRF
token, language) from the mount element's `data-*` attributes, and mounts onto a single
root node rendered by a deliberately minimal PHP view.

**Vue is bundled into each app.** Stud.IP 6 ships Vue only as a UMD/IIFE global and does
not ship Pinia, Vue Router or vue-i18n at all, so the ESM `import { … } from 'vue'` style
cannot be satisfied from the host. Quorum therefore bundles the whole Vue stack itself —
the same approach Stud.IP's own apps take. Each entry produces a prefixed, content-hashed
bundle (e.g. `quorum-courseapp-<hash>.js`) so it is easy to spot in network logs.

State is held in Pinia stores; async actions follow a `loading` / `error` flag convention.
The course app uses Vue Router with hash history, because Stud.IP only serves the plugin
index route — in-app navigation changes only the URL fragment and triggers no server
round-trip.

## Backend key concepts

- **PollType** (`lib/Polls/PollType.php`) is the single source of truth for question
  types (`mc`, `multi`, `scales`, `emoji`, `freitext`, `matrix`). Service validation,
  controllers, importers and the database default all check against the same constants.
- **Anonymous voting.** The student page and the answer-submission endpoint are public and
  identified only by a poll token. Mutating lecturer requests carry the Stud.IP CSRF
  token; the anonymous vote endpoint instead relies on a fail-closed same-origin check,
  since anonymous clients have no session CSRF token. No personally identifying data — no
  IP, no client hash — is stored with a response; protection against accidental double
  voting lives purely in the participant's browser (localStorage).
- **Live result sync.** While a poll runs, lecturer and presenter views fetch aggregated
  counts by polling a JSON snapshot endpoint on a short interval. Aggregation happens in
  SQL and returns only option ids and counts — never user ids or answer details.
- **Collections** group polls into reusable sets (templates) that can be created, ordered,
  archived and presented as a sequence.
- **Quiz scoring** (`lib/Quiz/QuizScorer.php`) supports marking options as correct and
  scoring responses, used by the quiz mode.
- **Export.** Results can be exported as CSV (`ResultsCsvExporter`) per poll or per
  collection, and as PDF (`ResultsPdfExporter`). A manual is also available as PDF.

## Internationalisation

Two independent layers, both shipping German and English:

- **PHP/UI strings** use a dedicated gettext domain `quorum` via the `_quorum()` helper
  (`lib/i18n.php`). The source language is German; `_quorum()` looks a string up in the
  plugin catalogue and **falls back to the Stud.IP core domain (`_()`)** when it is not
  found, so generic terms (Save, Cancel, …) inherit their core translation and are not
  duplicated. Catalogues live under `locale/<lang>/LC_MESSAGES/quorum.{po,mo}`. The helper
  degrades gracefully when `ext-gettext` is unavailable (CLI/tests).
- **Vue strings** use **vue-i18n** in composition mode, with catalogues in
  `resources/vue/locales/{de,en}.json`. vue-i18n is used (rather than the host's gettext
  plugin) so component tests run in Node without a build-time extraction step and the
  catalogues stay versioned in this repository. Keys follow a hierarchical pattern
  (e.g. `polls.loading`).

## Build

The published package already ships the built frontend bundles under `public/`, so a
plain checkout only needs the runtime dependencies:

```bash
composer install --no-dev   # PHP autoloader + runtime dependencies
npm install                 # JS toolchain (only to rebuild the bundles)
npm run build               # vite build → public/ (+ public/.vite/manifest.json)
npm run dev                 # watch mode (during development)
```

Vite writes content-hashed bundles into `public/` and a manifest. At request time the
PHP view resolves the entry name to its hashed URL through the manifest reader
(`lib/Vite/Manifest.php`), giving cache-busting without manual URL maintenance.

## Testing

The published package ships only runtime code and the built bundles — it contains no test
suite. The notes below are a **recommendation** for anyone developing or extending the
plugin, not a description of shipped files.

The layered architecture (see above) is what makes the code testable: services and domain
objects have no framework dependencies, so they can be exercised in isolation. A sensible
coverage split:

| Layer / concern | Tool | What to cover |
|---|---|---|
| Domain & services (PHP) | **PHPUnit** | `PollType` validation, the `*Service` classes, `QuizScorer`, CSV/PDF exporters, the Cliqr migrator (idempotency, fault tolerance). Pure unit tests — no database needed for the domain. |
| Repositories & migrations (PHP) | **PHPUnit** (integration) | single-query lookups against a test database, schema migrations apply/rollback cleanly. |
| Pinia stores & composables (JS) | **Vitest** + Vue Test Utils | the `loading`/`error` action convention, the localStorage double-vote guard in the polls store, and the SSE→polling fallback in `useLiveResults` (watchdog, backoff). |
| Vue components (JS) | **Vitest** + Vue Test Utils | rendering and interaction for the four apps and shared components. |
| Accessibility | **axe-core** | run inside the component and E2E tests; target WCAG 2.2 AA at the 375 px mobile breakpoint. |
| Critical end-to-end flows | **Playwright** | anonymous token voting, presenter keyboard navigation, participant live-sync. Run on a desktop and a mobile viewport. |
| Static analysis & style | **PHPStan** (level 5) and **PHP-CS-Fixer** (PSR-12) | catch type errors and keep a consistent style before review. |

The anonymous vote path and the live-result fallback are the highest-value targets: both
are user-facing, both have non-obvious failure modes, and both are easy to cover with fast
unit tests.

## Database

All plugin tables are prefixed `quorum_`. The schema is created and evolved by numbered
migrations under `migrations/` (`NN_*.php`), run through the plugin's migration tooling.

| Table | Holds |
|---|---|
| `quorum_polls` | polls: token, optional course binding, owner, question, type, options (JSON), lifecycle/expiry |
| `quorum_responses` | individual answers as JSON; no personally identifying data |
| `quorum_poll_collections` | collections / templates (name, owner, order) |
| `quorum_short_urls` | plugin-owned short links (fallback for hosts without native short URLs) |
| `quorum_migration_log` | audit trail for idempotent data imports |

Repositories use single-query lookups (e.g. aggregating counts for many polls in one
statement) to avoid N+1 access patterns.
