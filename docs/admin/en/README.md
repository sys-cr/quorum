# Quorum — Administration

This guide is aimed at the **Stud.IP administration** and covers the **ongoing operation**: configuring Quorum, privacy, deactivating/uninstalling, and troubleshooting. The one-time setup (installation, enabling, Cliqr migration) is covered by [Installation](../../install/en/README.md).

## Contents

- [Configuration: minimum role (`QUORUM_MIN_ROLE`)](#configuration-minimum-role-quorum_min_role)
- [Configuration: free-text blocklist (`QUORUM_FREITEXT_BLOCKLIST`)](#configuration-free-text-blocklist-quorum_freitext_blocklist)
- [Privacy](#privacy)
- [Deactivating & uninstalling](#deactivating--uninstalling)
- [Troubleshooting](#troubleshooting)

## Configuration: minimum role (`QUORUM_MIN_ROLE`)

Controls **from which Stud.IP system role** the workplace tile and the course-independent management are visible.

| | |
|---|---|
| Location | **Configuration → System configuration → Quorum → `QUORUM_MIN_ROLE`** |
| Default | `dozent` |
| Allowed values | `user`, `autor`, `tutor`, `dozent`, `admin`, `root` |

Invalid values (typos etc.) automatically fall back to the default `dozent` — so a misconfiguration cannot accidentally open the management to anonymous users. The entry is created on activation and removed on deactivation; if the plugin was already active before this state, a migration adds it retroactively.

Independently of this setting, the normal Stud.IP permission model always applies in the course context: the "Quorum" tab requires at least **tutor rights** in the respective course.

## Configuration: free-text blocklist (`QUORUM_FREITEXT_BLOCKLIST`)

Filters anonymous **free-text answers before they are stored**: if an answer contains one of the configured blocked terms, it is rejected outright.

| | |
|---|---|
| Location | **Configuration → System configuration → Quorum → `QUORUM_FREITEXT_BLOCKLIST`** |
| Format | comma-separated terms (e.g. `term1, term2`) |
| Default | empty = moderation off |

The blocklist is the first of two moderation stages: instructors can additionally **remove** individual already-stored free-text answers on the results page (see [Results, moderation & export](../../user/en/manual.md#ergebnisse)).

## Privacy

- Students participate **anonymously**; no Stud.IP login is required.
- Protection against accidental double voting is kept **purely in the browser** (localStorage). Quorum does **no IP tracking** and stores **no** client- or person-related identifier per answer — only the selection or free text and a timestamp.
- **No** personal data is stored in logs or URLs; CSV exports contain only aggregated numbers or anonymous free texts.

## Deactivating & uninstalling

On **deactivation**, Quorum removes its configuration entries. The data tables are kept so nothing is lost when re-enabling. A complete uninstall (dropping the tables) should be done deliberately and with a backup — the Quorum data lives in the tables `quorum_polls`, `quorum_responses`, `quorum_poll_collections`, `quorum_short_urls` and `quorum_migration_log`.

## Troubleshooting

**"Plugin does not implement a valid interface" on registration.** — Path and manifest disagree. The plugin must live under `…/studip-quorum/QuorumStudipPlugin/`, matching `origin=studip-quorum` and `pluginclassname=QuorumStudipPlugin`.

**The live results only update every few seconds instead of instantly.** — Behind nginx, the stream location rule is missing (see [Installation](../../install/en/README.md#web-server-live-results-nginx-only)). Functionally this is harmless (polling fallback), but push is inactive.

**The Quorum tile is missing for some instructors on the workplace.** — Their role does not meet `QUORUM_MIN_ROLE`. Check the value or adjust the role.
