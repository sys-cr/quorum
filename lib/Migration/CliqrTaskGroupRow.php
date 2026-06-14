<?php

declare(strict_types=1);

namespace Quorum\Migration;

/**
 * A Cliqr collection (`etask_assignments.type = 'cliqr:task-group'`) with its
 * ordered member questions. Source for the Quorum collection migration.
 *
 * - `title`/`userId` come from the associated `etask_tests` (test_id).
 * - `tasks` are the member tasks in `etask_test_tasks.position` order, each as
 *   a {@see CliqrTaskRow} (reuses the question mapping of the single-voting path).
 */
final class CliqrTaskGroupRow
{
    /**
     * @param list<CliqrTaskRow> $tasks
     */
    public function __construct(
        public readonly int    $etaskAssignmentId,
        public readonly string $seminarId,
        public readonly string $userId,
        public readonly string $title,
        public readonly array  $tasks,
    ) {
    }
}
