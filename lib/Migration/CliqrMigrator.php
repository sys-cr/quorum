<?php

declare(strict_types=1);

namespace Quorum\Migration;

use Quorum\Polls\Collection;
use Quorum\Polls\CollectionsRepository;
use Quorum\Polls\Poll;
use Quorum\Polls\PollsRepository;
use Quorum\Polls\PollType;
use Random\Randomizer;
use Throwable;

/**
 * Migrates Cliqr eTask data into `quorum_polls`.
 *
 * Behavior:
 *   - Quarantine by design: we only READ from `etask_*` and write to
 *     `quorum_*`. The original stays untouched.
 *   - Idempotent: `MigrationLog` tracks already-migrated `etask_assignments.id`s.
 *   - Fault-tolerant: a broken record is marked in the report and migration
 *     continues.
 *   - dry-run: simulates without writes but still detects conflicts/errors.
 */
final class CliqrMigrator
{
    private const MAX_TOKEN_ATTEMPTS = 5;

    public function __construct(
        private readonly CliqrSourceRepository $source,
        private readonly PollsRepository       $target,
        private readonly MigrationLog          $log,
        /** Optional: without it, collections (task-groups) are skipped. */
        private readonly ?CollectionsRepository $collections = null,
    ) {
    }

    public function detect(): int
    {
        return $this->source->countCliqrAssignments();
    }

    public function detectTaskGroups(): int
    {
        return $this->source->countCliqrTaskGroups();
    }

    public function migrate(bool $dryRun = false): MigrationReport
    {
        $migrated = 0;
        $skipped  = [];
        $errors   = [];

        foreach ($this->source->iterateCliqrTasks() as $row) {
            if ($this->log->isMigrated($row->etaskAssignmentId)) {
                $skipped[] = [
                    'etask_assignment_id' => $row->etaskAssignmentId,
                    'reason'              => 'already_migrated',
                ];
                continue;
            }

            try {
                $this->persistTask($row, $dryRun);
                $migrated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'etask_assignment_id' => $row->etaskAssignmentId,
                    'error'               => $e->getMessage(),
                ];
            }
        }

        // Collections (cliqr:task-group → Quorum collection) — only if a
        // CollectionsRepository was injected.
        $collectionsMigrated = $this->migrateTaskGroups($dryRun, $skipped, $errors);

        return new MigrationReport(
            migrated:            $migrated,
            skipped:             $skipped,
            errors:              $errors,
            dryRun:              $dryRun,
            collectionsMigrated: $collectionsMigrated,
        );
    }

    /**
     * Persists one Cliqr task as a Quorum poll definition (NO responses —
     * historical Cliqr votes are deliberately not carried over).
     *
     * Token collisions (UNIQUE `token`) are extremely rare but must not lose a
     * record: on collision, retry with a freshly rolled token (up to
     * `MAX_TOKEN_ATTEMPTS`).
     */
    private function persistTask(CliqrTaskRow $row, bool $dryRun): void
    {
        for ($attempt = 0; ; $attempt++) {
            $poll = $this->mapToPoll($row);
            try {
                if (!$dryRun) {
                    $this->target->savePoll($poll);
                    $this->log->markMigrated($row->etaskAssignmentId, $poll->id);
                }
                return;
            } catch (Throwable $e) {
                if (self::isDuplicateTokenError($e) && $attempt + 1 < self::MAX_TOKEN_ATTEMPTS) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Migrates Cliqr collections (`cliqr:task-group`) to Quorum collections:
     * one collection per group (owner/name from the associated test) + the
     * member questions as polls in `etask_test_tasks.position` order.
     * Idempotent via the MigrationLog (assignment ID → collection ID).
     *
     * @param array<int,array<string,mixed>> $skipped
     * @param array<int,array<string,mixed>> $errors
     */
    private function migrateTaskGroups(bool $dryRun, array &$skipped, array &$errors): int
    {
        if ($this->collections === null) {
            return 0;
        }

        $migrated = 0;
        foreach ($this->source->iterateCliqrTaskGroups() as $group) {
            if ($this->log->isMigrated($group->etaskAssignmentId)) {
                $skipped[] = [
                    'etask_assignment_id' => $group->etaskAssignmentId,
                    'reason'              => 'already_migrated',
                ];
                continue;
            }
            try {
                if (!$dryRun) {
                    $collection = new Collection(
                        id:          self::generateId(),
                        userId:      $group->userId,
                        name:        $group->title !== '' ? $group->title : 'Cliqr-Sammlung',
                        description: null,
                        mkdate:      time(),
                        chdate:      time(),
                    );
                    $this->collections->save($collection);

                    $position = 0;
                    foreach ($group->tasks as $taskRow) {
                        $poll = $this->mapToPoll($taskRow);
                        $this->target->savePoll($poll);
                        $this->collections->setPollMembership($poll->id, $collection->id, $position++);
                    }
                    // Log as migrated only AFTER all members — an abort midway
                    // lands in the report and the re-run retries it (not
                    // transactional; a clean solution needs an injected
                    // transaction runner).
                    $this->log->markMigrated($group->etaskAssignmentId, $collection->id);
                }
                $migrated++;
            } catch (Throwable $e) {
                $errors[] = [
                    'etask_assignment_id' => $group->etaskAssignmentId,
                    'error'               => $e->getMessage(),
                ];
            }
        }
        return $migrated;
    }

    /**
     * @throws \JsonException
     * @throws \UnexpectedValueException
     */
    private function mapToPoll(CliqrTaskRow $row): Poll
    {
        $task = json_decode($row->taskJson, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($task)) {
            throw new \UnexpectedValueException('Cliqr-Task-JSON ist kein Objekt.');
        }

        // IMPORTANT: real Cliqr stores `etask_tasks.type` as
        // 'multiple-choice'/'scales' (JS defaults), NOT 'mc'. Both spellings
        // are normalized here.
        $normalized = self::normalizeTaskType($row->taskType);
        $options = match ($normalized) {
            PollType::MC     => self::mapMcOptions($task),
            PollType::SCALES => self::mapScalesOptions($task),
            default          => throw new \UnexpectedValueException(sprintf(
                'Unbekannter Cliqr-TaskType "%s" — nur Multiple-Choice und Scales werden migriert.',
                $row->taskType,
            )),
        };

        $type = $normalized;
        // Cliqr MC distinguishes single- vs. multi-select via the inner task
        // JSON field `type` ('single'|'multiple'), NOT via `etask_tasks.type`.
        // 'multiple' → Quorum multi-select; the options are identical.
        if ($normalized === PollType::MC && ($task['type'] ?? '') === 'multiple') {
            $type = PollType::MULTI;
        }

        return new Poll(
            id:        self::generateId(),
            token:     self::generateToken(),
            seminarId: $row->seminarId,
            userId:    $row->userId,
            question:  $row->taskTitle,
            type:      $type,
            options:   $options,
            // Migrated polls arrive as a reusable template, NOT running —
            // without responses an "active" survey makes no sense; restart in
            // Quorum if needed.
            isActive:  false,
            mkdate:    time(),
            chdate:    time(),
        );
    }

    /**
     * Normalizes the Cliqr `etask_tasks.type` to a Quorum `PollType`. Real
     * Cliqr uses 'multiple-choice'/'scales'; 'mc' is additionally accepted.
     * Unknown values pass through unchanged → the match logic throws.
     */
    private static function normalizeTaskType(string $cliqrType): string
    {
        return match ($cliqrType) {
            'multiple-choice', 'mc' => PollType::MC,
            'scales'                => PollType::SCALES,
            default                 => $cliqrType,
        };
    }

    /**
     * @param  array<string,mixed> $task
     * @return list<array{id: string, label: string}>
     */
    private static function mapMcOptions(array $task): array
    {
        $answers = $task['answers'] ?? [];
        if (!is_array($answers) || $answers === []) {
            throw new \UnexpectedValueException('Cliqr-MC-Task ohne `answers`.');
        }

        $options = [];
        foreach ($answers as $i => $answer) {
            $text = is_array($answer) ? ($answer['text'] ?? '') : '';
            $options[] = [
                'id'    => (string) $i,
                'label' => is_string($text) ? $text : '',
            ];
        }
        return $options;
    }

    /**
     * @param  array<string,mixed> $task
     * @return list<array{id: string, label: string}>
     */
    private static function mapScalesOptions(array $task): array
    {
        $statements = $task['statements'] ?? [];
        if (!is_array($statements) || $statements === []) {
            throw new \UnexpectedValueException('Cliqr-Scales-Task ohne `statements`.');
        }

        // Scale range as options: lrange_value..hrange_value
        $low  = (int) ($task['lrange_value'] ?? 1);
        $high = (int) ($task['hrange_value'] ?? 5);
        if ($high < $low) {
            [$low, $high] = [$high, $low];
        }

        $options = [];
        for ($v = $low; $v <= $high; $v++) {
            $options[] = ['id' => (string) $v, 'label' => (string) $v];
        }
        return $options;
    }

    /**
     * Detects a MySQL/MariaDB UNIQUE violation (SQLSTATE 23000), usually from
     * a token collision on insert.
     */
    private static function isDuplicateTokenError(Throwable $e): bool
    {
        if ($e instanceof \PDOException && (string) $e->getCode() === '23000') {
            return true;
        }
        return stripos($e->getMessage(), 'Duplicate entry') !== false;
    }

    private static function generateId(): string
    {
        return bin2hex((new Randomizer())->getBytes(16));
    }

    private static function generateToken(): string
    {
        // 8 base62 chars — like our ShortUrl adapter
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $out   = '';
        $rand  = new Randomizer();
        for ($i = 0; $i < 8; $i++) {
            $out .= $chars[$rand->getInt(0, 61)];
        }
        return $out;
    }
}
