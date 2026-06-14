<?php

declare(strict_types=1);

namespace Quorum\Migration;

use DBManager;

/**
 * Audit trail of migrated Cliqr records. Every successfully migrated
 * `etask_assignments.id` lands here — on a second run it is recognized as
 * "already migrated" and skipped.
 *
 * Table schema (see `migrations/03_create_quorum_migration_log.php`):
 *   - etask_assignment_id (UNIQUE) — source ID
 *   - quorum_poll_id      — target ID in `quorum_polls`
 *   - mkdate              — when migrated
 */
class MigrationLog
{
    public function isMigrated(int $etaskAssignmentId): bool
    {
        $stmt = DBManager::get()->prepare(
            'SELECT 1 FROM quorum_migration_log WHERE etask_assignment_id = ?'
        );
        $stmt->execute([$etaskAssignmentId]);
        return $stmt->fetchColumn() !== false;
    }

    public function markMigrated(int $etaskAssignmentId, string $quorumPollId): void
    {
        $stmt = DBManager::get()->prepare(
            'INSERT IGNORE INTO quorum_migration_log
                (etask_assignment_id, quorum_poll_id, mkdate)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$etaskAssignmentId, $quorumPollId, time()]);
    }
}
