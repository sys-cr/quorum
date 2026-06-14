# Quorum — Installation

This guide is aimed at the **Stud.IP administration** and covers the **one-time setup**: installing and enabling the plugin, and taking over data from the predecessor plugin Cliqr. The ongoing operation (configuration, privacy, troubleshooting) is covered by [Administration](../../admin/en/README.md).

> The plugin's architecture and layout are described in the [developer documentation](../../developer/en/README.md).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Web server: live results (nginx only)](#web-server-live-results-nginx-only)
- [Migrating from Cliqr](#migrating-from-cliqr)
- [Transferring short links after a Stud.IP upgrade](#transferring-short-links-after-a-studip-upgrade)

## Requirements

| Requirement | Value |
|---|---|
| Stud.IP | 6.0 to 6.x (manifest: `studipMinVersion=6.0`) |
| PHP | ≥ 8.1 |
| PHP extension | `ext-gettext` |
| Database | MariaDB/MySQL (as required by Stud.IP) |
| Web server | Apache or nginx — usually no extra configuration; with nginx, an optional rule for live results (see below) |

Quorum loads **no** external services (no Google/YOURLS/Polr shorteners, no external fonts or CDNs). All assets are served from your own server.

## Installation

### Variant A — plugin package via the Stud.IP UI

1. Zip the contents of `quorum-release/` (the ZIP root must be `plugin.manifest`).
2. In Stud.IP as administrator: **Admin → Plugins → Upload plugin** and choose the ZIP file.
3. Stud.IP unpacks the plugin to `public/plugins_packages/studip-quorum/QuorumStudipPlugin/`. Important: origin (`studip-quorum`) and class name (`QuorumStudipPlugin`) must match the path.

### Variant B — manually on the server

1. Copy `quorum-release/` to `public/plugins_packages/studip-quorum/QuorumStudipPlugin/`.
2. Install PHP dependencies: `composer install --no-dev` in the plugin directory.
3. Register:
   ```bash
   php cli/studip plugin:register \
       public/plugins_packages/studip-quorum/QuorumStudipPlugin
   ```
   Expected output: "The plugin was successfully registered."

> Stud.IP 6 uses Symfony Console — the CLI is called `cli/studip` **without** a `.php` suffix.

### Enabling

`plugin:register` only creates the database entry. Then enable the plugin in the **plugin management** in the browser (or via `php cli/studip plugin:enable …`). On activation, Quorum creates its database tables and configuration automatically.

### Verification

```sql
SELECT pluginname, pluginclassname, pluginpath
FROM plugins WHERE pluginclassname = 'QuorumStudipPlugin';
-- Quorum | QuorumStudipPlugin | studip-quorum/QuorumStudipPlugin
```

Afterwards, the **"Quorum"** tab appears in every course and the Quorum tile on the workplace (depending on the minimum role, see [Administration](../../admin/en/README.md)).

## Web server: live results (nginx only)

The live results use server-sent events over a long-lived HTTP connection. **No web-server configuration is normally required:** Quorum sets `X-Accel-Buffering: no` in the response header, which makes nginx disable output buffering for the stream route on its own; with Apache there is nothing to do anyway. And should the push fail unexpectedly, the frontend automatically falls back to 2-second polling — the results still update, just polled instead of pushed.

An explicit nginx rule is therefore **optional**. It only helps if a particularly restrictive proxy configuration ignores the `X-Accel-Buffering` header and the live view noticeably stays in polling mode. In that case, disable buffering for the stream route explicitly:

```nginx
location ~ ^/plugins\.php/quorumstudipplugin/api/stream/[^/]+$ {
    proxy_buffering off;
    proxy_read_timeout 3600s;
}
```

Of these, effectively only `proxy_buffering off` is required. The `proxy_read_timeout` is mere precaution: Quorum sends a heartbeat every 30 seconds, so the connection survives nginx's default timeout anyway.

## Migrating from Cliqr

Quorum can take over existing **Cliqr definitions** — individual polls (multiple choice / scale) **and** collections. Historical **answers are deliberately not** taken over (votes of expired Cliqr polls are no longer relevant); migrated surveys arrive as non-running templates. The original Cliqr data stays **untouched** — Cliqr and Quorum can run side by side afterwards.

### Running it (Stud.IP 6.1+)

```bash
# Preview without writing:
php cli/studip quorum:migrate-cliqr --dry-run

# Real run:
php cli/studip quorum:migrate-cliqr
```

On **Stud.IP 6.0** (no plugin CLI hook) there is a standalone wrapper:

```bash
php public/plugins_packages/studip-quorum/QuorumStudipPlugin/scripts/migrate-cliqr.php [--dry-run]
```

Properties: **idempotent** (a second run writes nothing), **fault-tolerant** (broken records are flagged in the report, the migration carries on), **source-preserving** (it only reads from the `etask_*` tables, never writes to them). An audit trail lands in `quorum_migration_log`.

Technical details and mapping table: [developer documentation](../../developer/en/README.md).

## Transferring short links after a Stud.IP upgrade

Quorum detects the Stud.IP version automatically and uses the native `short_urls` table from 6.2 onwards, and its own table on 6.0/6.1. After an upgrade from 6.0/6.1 to 6.2 you can transfer the plugin's own short links to the native management — they then appear for instructors under "My short links":

```bash
php cli/studip quorum:migrate-short-urls --dry-run   # preview
php cli/studip quorum:migrate-short-urls             # real run
```

This process is also idempotent and alias-preserving — **existing QR codes keep working**. There is no hurry: as long as the command has not run, composite mode stays active and all aliases remain resolvable.
