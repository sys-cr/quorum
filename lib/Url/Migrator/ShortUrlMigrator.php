<?php

declare(strict_types=1);

namespace Quorum\Url\Migrator;

use Quorum\Url\ShortUrlAdapter;
use Throwable;

/**
 * Copies entries from the Quorum fallback table (`quorum_short_urls`) into
 * the Stud.IP-native `short_urls`. Invoked by the CLI command
 * `quorum:migrate-short-urls` (see `Quorum\Cli\MigrateShortUrlsCommand`).
 *
 * Behavior:
 *   - Idempotent: a second run after success → empty source → 0 migrated.
 *   - Conflict (alias already in the Stud.IP table): the entry stays in
 *     Quorum; the `CompositeShortUrlAdapter` still resolves it.
 *   - Success: Stud.IP insert first, then Quorum delete — a crash in between
 *     would retry the entry on the next run.
 *   - dry-run: simulates without writes; conflicts are still detected and reported.
 */
final class ShortUrlMigrator
{
    public function __construct(
        private readonly ShortUrlAdapter $native,
        private readonly ShortUrlAdapter $quorum,
    ) {
    }

    public function migrate(bool $dryRun = false): MigrationReport
    {
        $migrated  = 0;
        $conflicts = [];
        $errors    = [];

        foreach ($this->quorum->iterateAll() as $entry) {
            if ($this->native->existsAlias($entry->alias)) {
                $conflicts[] = [
                    'alias'  => $entry->alias,
                    'reason' => 'Alias bereits in Stud.IP-`short_urls` vergeben — Quorum-Eintrag bleibt für Composite-Resolver.',
                ];
                continue;
            }

            if ($dryRun) {
                $migrated++;
                continue;
            }

            try {
                // import() rather than create() — otherwise a new alias would
                // be generated and all existing QR codes would be dead.
                $this->native->import($entry);
                $this->quorum->deleteByAlias($entry->alias);
                $migrated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'alias' => $entry->alias,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return new MigrationReport(
            migrated:  $migrated,
            conflicts: $conflicts,
            errors:    $errors,
            dryRun:    $dryRun,
        );
    }
}
