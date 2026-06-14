<?php

declare(strict_types=1);

namespace Quorum\Migration;

/**
 * Status report of a `CliqrMigrator` run.
 *
 * @phpstan-type SkippedEntry array{etask_assignment_id: int, reason: string}
 * @phpstan-type ErrorEntry   array{etask_assignment_id: int, error: string}
 */
final class MigrationReport
{
    /**
     * @param list<SkippedEntry> $skipped  already-migrated or explicitly skipped entries
     * @param list<ErrorEntry>   $errors   records that failed due to broken JSON etc.
     */
    public function __construct(
        public readonly int   $migrated,
        public readonly array $skipped,
        public readonly array $errors,
        public readonly bool  $dryRun,
        /** Number of migrated Cliqr collections (cliqr:task-group → Collection). */
        public readonly int   $collectionsMigrated = 0,
    ) {
    }
}
