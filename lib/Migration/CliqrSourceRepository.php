<?php

declare(strict_types=1);

namespace Quorum\Migration;

use DBManager;

/**
 * Reads Cliqr-specific data from the Stud.IP eTask schema. Deliberately
 * read-only — the migrator must never alter the original data.
 *
 * Detection strategy: `etask_assignments.type LIKE 'cliqr:%'`. Cliqr uses two
 * sub-types (`cliqr:voting` for polls, `cliqr:task-group` for collections).
 */
class CliqrSourceRepository
{
    public function countCliqrAssignments(): int
    {
        if (!self::tableExists('etask_assignments')) {
            return 0;
        }
        $stmt = DBManager::get()->query(
            "SELECT COUNT(*) FROM etask_assignments WHERE type = 'cliqr:voting'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return iterable<CliqrTaskRow>
     */
    public function iterateCliqrTasks(): iterable
    {
        if (!self::tableExists('etask_assignments') || !self::tableExists('etask_tasks')) {
            return;
        }

        // JOIN via etask_test_tasks: each Cliqr voting assignment points to a
        // test containing one or more tasks. For Cliqr polls it is 1:1 (one
        // question per voting).
        $sql = '
            SELECT  a.id          AS assignment_id,
                    a.range_id    AS seminar_id,
                    a.type        AS assignment_type,
                    a.active      AS is_active,
                    t.id          AS task_id,
                    t.user_id     AS user_id,
                    t.type        AS task_type,
                    t.title       AS task_title,
                    t.task        AS task_json
              FROM  etask_assignments a
              JOIN  etask_test_tasks tt ON tt.test_id = a.test_id
              JOIN  etask_tasks       t  ON t.id      = tt.task_id
             WHERE  a.type = "cliqr:voting"
             ORDER  BY a.id ASC
        ';

        $stmt = DBManager::get()->query($sql);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield new CliqrTaskRow(
                etaskAssignmentId: (int) $row['assignment_id'],
                etaskTaskId:       (int) $row['task_id'],
                seminarId:         (string) ($row['seminar_id'] ?? ''),
                userId:            (string) $row['user_id'],
                assignmentType:    (string) $row['assignment_type'],
                isActive:          (bool) $row['is_active'],
                taskType:          (string) $row['task_type'],
                taskTitle:         (string) $row['task_title'],
                taskJson:          (string) $row['task_json'],
            );
        }
    }


    public function countCliqrTaskGroups(): int
    {
        if (!self::tableExists('etask_assignments')) {
            return 0;
        }
        $stmt = DBManager::get()->query(
            "SELECT COUNT(*) FROM etask_assignments WHERE type = 'cliqr:task-group'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cliqr collections (`cliqr:task-group`) including their ordered member
     * questions. Chain: assignment → test (title/owner) → test_tasks (position)
     * → tasks. An empty collection (no questions) is skipped — the JOIN yields
     * no row for it.
     *
     * @return iterable<CliqrTaskGroupRow>
     */
    public function iterateCliqrTaskGroups(): iterable
    {
        if (!self::tableExists('etask_assignments') || !self::tableExists('etask_tasks')) {
            return;
        }

        $sql = '
            SELECT  a.id        AS assignment_id,
                    a.range_id  AS seminar_id,
                    te.user_id  AS owner_id,
                    te.title    AS group_title,
                    t.id        AS task_id,
                    t.user_id   AS task_user_id,
                    t.type      AS task_type,
                    t.title     AS task_title,
                    t.task      AS task_json
              FROM  etask_assignments a
              JOIN  etask_tests       te ON te.id      = a.test_id
              JOIN  etask_test_tasks  tt ON tt.test_id = a.test_id
              JOIN  etask_tasks       t  ON t.id       = tt.task_id
             WHERE  a.type = "cliqr:task-group"
             ORDER  BY a.id ASC, tt.position ASC
        ';

        $stmt    = DBManager::get()->query($sql);
        $current = null;   // currently buffered assignment_id
        $meta    = [];
        $tasks   = [];

        $build = static function (array $meta, array $tasks): CliqrTaskGroupRow {
            return new CliqrTaskGroupRow(
                etaskAssignmentId: (int) $meta['assignment_id'],
                seminarId:         (string) ($meta['seminar_id'] ?? ''),
                userId:            (string) ($meta['owner_id'] ?? ''),
                title:             (string) ($meta['group_title'] ?? ''),
                tasks:             $tasks,
            );
        };

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $aid = (int) $row['assignment_id'];
            if ($current !== $aid) {
                if ($current !== null) {
                    yield $build($meta, $tasks);
                }
                $current = $aid;
                $meta    = $row;
                $tasks   = [];
            }
            $tasks[] = self::taskRowFrom($aid, $row);
        }
        if ($current !== null) {
            yield $build($meta, $tasks);
        }
    }

    /**
     * Builds a member `CliqrTaskRow` from a collection result row. Collection
     * tasks are never active (`isActive = false`) and carry the fixed type
     * `cliqr:task-group`. Extracted to keep the grouping loop lean.
     *
     * @param array<string,mixed> $row
     */
    private static function taskRowFrom(int $assignmentId, array $row): CliqrTaskRow
    {
        return new CliqrTaskRow(
            etaskAssignmentId: $assignmentId,
            etaskTaskId:       (int) $row['task_id'],
            seminarId:         (string) ($row['seminar_id'] ?? ''),
            userId:            (string) $row['task_user_id'],
            assignmentType:    'cliqr:task-group',
            isActive:          false,
            taskType:          (string) $row['task_type'],
            taskTitle:         (string) $row['task_title'],
            taskJson:          (string) $row['task_json'],
        );
    }

    private static function tableExists(string $table): bool
    {
        $stmt = DBManager::get()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }
}
