<?php

declare(strict_types=1);

namespace Quorum\Url\Migrator;

/**
 * Status report of a `ShortUrlMigrator` run.
 *
 * Fields intentionally public + readonly: makes JSON serialization trivial so
 * the CLI command can log the report in machine-readable form.
 */
final class MigrationReport
{
    /**
     * @param list<array{alias: string, reason: string}> $conflicts
     * @param list<array{alias: string, error:  string}> $errors
     */
    public function __construct(
        public readonly int   $migrated,
        public readonly array $conflicts,
        public readonly array $errors,
        public readonly bool  $dryRun,
    ) {
    }
}
