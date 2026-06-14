<?php

declare(strict_types=1);

namespace Quorum\Migration;

/**
 * Raw DTO for a Cliqr record from the Stud.IP eTask schema: one Cliqr voting
 * assignment + its associated task.
 *
 * Cliqr stores its voting data in the standard eTask tables:
 *   - `etask_assignments.type = 'cliqr:voting'` marks the poll sessions
 *   - `etask_tasks` holds the question (with a JSON-encoded `task` schema)
 *   - `etask_test_tasks` joins test → task (m:n)
 */
final class CliqrTaskRow
{
    public function __construct(
        public readonly int    $etaskAssignmentId,
        public readonly int    $etaskTaskId,
        public readonly string $seminarId,
        public readonly string $userId,
        public readonly string $assignmentType,
        public readonly bool   $isActive,
        public readonly string $taskType,    // 'mc' | 'scales'
        public readonly string $taskTitle,
        public readonly string $taskJson,
    ) {
    }
}
