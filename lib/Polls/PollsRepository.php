<?php

declare(strict_types=1);

namespace Quorum\Polls;

use Quorum\Models\QuorumPoll;
use Quorum\Models\QuorumResponse;

/**
 * Persistence adapter for polls + responses.
 *
 * Unit tests mock this class — see `PollsServiceTest`. The actual
 * SimpleORMap calls require the Stud.IP bootstrap and are covered by
 * integration tests.
 */
class PollsRepository
{
    public function findByToken(string $token): ?Poll
    {
        $row = QuorumPoll::findOneBySQL('token = ?', [$token]);
        return $row ? self::toPoll($row) : null;
    }

    public function findById(string $id): ?Poll
    {
        $row = QuorumPoll::find($id);
        return $row ? self::toPoll($row) : null;
    }

    /**
     * Auto-follow anchor for the participant live sync: returns the currently
     * active sibling question of the same collection — or null. Follow-up
     * runs (comparison chains) are excluded; their tokens go through the
     * blind-mode flow. With several active questions the most recently
     * started one wins (`setActive` updates `chdate`).
     */
    public function findActivePollInCollection(string $collectionId, string $excludePollId): ?Poll
    {
        $row = QuorumPoll::findOneBySQL(
            'collection_id = ? AND id != ? AND parent_poll_id IS NULL
             AND is_active = 1 AND archived_at IS NULL
             AND (expires_at IS NULL OR expires_at > ?)
             ORDER BY chdate DESC',
            [$collectionId, $excludePollId, time()]
        );
        return $row ? self::toPoll($row) : null;
    }

    /**
     * The currently active round of a compare chain (root `$rootId` or one of
     * its follow-up rounds). Basis for auto-follow: if someone scanned the QR
     * code of the first round and the lecturer starts a follow-up round via
     * "Restart", the phone should move there automatically (like with
     * collections). Newest active round first.
     */
    public function findActivePollInChain(string $rootId): ?Poll
    {
        $row = QuorumPoll::findOneBySQL(
            '(id = ? OR parent_poll_id = ?)
             AND is_active = 1 AND archived_at IS NULL
             AND (expires_at IS NULL OR expires_at > ?)
             ORDER BY chdate DESC',
            [$rootId, $rootId, time()]
        );
        return $row ? self::toPoll($row) : null;
    }

    /**
     * Aggregates a poll's responses as an option-ID → count map.
     *
     * Used by the `LiveResultsStreamer` (SSE) for live counts. Returns only
     * aggregates without PII — no user IDs, no response detail data.
     *
     * Applies only to selection types (`PollType::SELECTION` = mc/scales/emoji)
     * that store a `$.selected` token. Matrix and free-text polls have
     * dedicated endpoints (`aggregateMatrixCountsForPoll` /
     * `findFreitextResponses`) and deliberately return an empty map here.
     *
     * @return array<string, int>
     */
    public function aggregateCountsForPoll(string $pollId): array
    {
        // Done in PHP rather than a SQL `JSON_EXTRACT('$.selected')` GROUP BY,
        // because multi-select stores the answer as an array
        // (`{selected:["a","b"]}`), which cannot be portably (MariaDB 10.2)
        // "unfolded" in SQL. For single-select the result is identical. Per-poll
        // response counts are class-sized (hundreds), so the iteration is fine.
        $stmt = \DBManager::get()->prepare(
            'SELECT response FROM quorum_responses WHERE poll_id = ?'
        );
        $stmt->execute([$pollId]);

        return self::countSelections($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Pure DB-free tally: list of raw response JSONs → `option ID → count`.
     * Handles single- and multi-select, dedupes per response. Extracted so
     * the multi logic is unit-testable without a DB.
     *
     * @param  list<string|null> $responseJsons
     * @return array<string, int>
     */
    public static function countSelections(array $responseJsons): array
    {
        $counts = [];
        foreach ($responseJsons as $json) {
            self::tallySelected($counts, (string) $json);
        }
        return $counts;
    }

    /**
     * Tallies the options selected in ONE response into `$counts` — handles
     * single-select (`{selected:"id"}`) AND multi-select (`{selected:["a","b"]}`).
     * Duplicates within one response count only once. Responses without
     * `selected` (matrix/free text) are ignored.
     *
     * @param array<string,int> $counts
     */
    private static function tallySelected(array &$counts, string $responseJson): void
    {
        $data = json_decode($responseJson, true);
        if (!is_array($data) || !array_key_exists('selected', $data)) {
            return;
        }
        $selected = $data['selected'];
        $ids      = is_array($selected) ? $selected : [$selected];
        $seen     = [];
        foreach ($ids as $id) {
            if (!is_scalar($id)) continue;
            $id = (string) $id;
            if ($id === '' || isset($seen[$id])) continue;
            $seen[$id]    = true;
            $counts[$id]  = ($counts[$id] ?? 0) + 1;
        }
    }

    /**
     * Own polls for the workplace widget.
     *
     * Returns all root polls (`parent_poll_id IS NULL`) created by `$userId`,
     * filtered by archive status:
     *   - `'active'`:  `archived_at IS NULL`
     *   - `'archive'`: `archived_at IS NOT NULL`
     *   - `'all'`:     both
     *
     * **One** query with `LEFT JOIN seminare`, `LEFT JOIN COUNT(*)` for
     * responses and `LEFT JOIN COUNT(*)` for follow-up rounds — all in one
     * round trip (no N+1, even with 50+ polls).
     *
     * No extra permission check at repository level — the caller (controller)
     * supplied `$userId` from `$GLOBALS['user']->id`, hence implicitly
     * "own data".
     *
     * @return list<PollSummary> sorted: active first, then by `mkdate` descending
     */
    public function findSummariesByUser(string $userId, string $view = 'active'): array
    {
        // Owner filter: own polls only (caller supplied $userId from the session).
        return $this->findSummaries('p.user_id = ?', $userId, $view);
    }

    /**
     * A course's polls for the course tab (course-app).
     *
     * Like `findSummariesByUser`, but course-scoped: returns ALL root polls
     * of course `$seminarId` (not just the calling user's) so co-teachers see
     * the same state in the course tab. Permission (tutor level in the course)
     * is checked by the controller.
     *
     * Default view `all` — the course tab distinguishes active/finished
     * client-side via `is_active`, not via separate endpoints.
     *
     * @return list<PollSummary>
     */
    public function findSummariesBySeminar(string $seminarId, string $view = 'all'): array
    {
        return $this->findSummaries('p.seminar_id = ?', $seminarId, $view);
    }

    /**
     * Lean list of a course's polls for the student view: only top-level polls,
     * NEVER archived ones. Returns the effective active status (flagged active
     * AND not expired) and the result-visibility flag — the caller splits
     * active/finished and filters the finished ones by `results_public`.
     *
     * @return list<array{id: string, token: string, question: string, type: string, is_active: bool, results_public: bool, mkdate: int}>
     */
    public function findStudentCoursePolls(string $seminarId): array
    {
        $sql = "SELECT p.id, p.token, p.question, p.type,
                       (p.is_active = 1 AND (p.expires_at IS NULL OR p.expires_at > ?)) AS is_active,
                       p.results_public, p.mkdate
                  FROM quorum_polls p
                 WHERE p.seminar_id = ?
                   AND p.parent_poll_id IS NULL
                   AND p.archived_at IS NULL
                 ORDER BY is_active DESC, p.mkdate DESC";

        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([time(), $seminarId]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id'             => (string) $row['id'],
                'token'          => (string) $row['token'],
                'question'       => (string) $row['question'],
                'type'           => (string) $row['type'],
                'is_active'      => (bool) $row['is_active'],
                'results_public' => (bool) $row['results_public'],
                'mkdate'         => (int) $row['mkdate'],
            ];
        }
        return $out;
    }

    /**
     * Shared query behind `findSummariesByUser`/`findSummariesBySeminar`.
     *
     * `$ownerWhere` is a hardcoded internal WHERE clause with exactly one `?`
     * placeholder (no user input) — the value is bound.
     *
     * @return list<PollSummary>
     */
    private function findSummaries(string $ownerWhere, string $ownerValue, string $view): array
    {
        $viewWhere = match ($view) {
            'archive' => 'p.archived_at IS NOT NULL',
            'all'     => '1=1',
            default   => 'p.archived_at IS NULL',
        };

        // Report `is_active` as the EFFECTIVE status — flagged active AND not
        // expired. Otherwise the teacher list keeps showing a time-expired
        // poll as "running" until the lazy auto-stop (on next token access)
        // kicks in. `?` = current server time.
        $sql = "SELECT p.id, p.token, p.seminar_id, p.question, p.type, p.quiz_mode,
                       (p.is_active = 1 AND (p.expires_at IS NULL OR p.expires_at > ?)) AS is_active,
                       p.mkdate, p.archived_at,
                       COALESCE(s.Name, '') AS seminar_name,
                       COALESCE(c.cnt, 0)   AS response_count,
                       COALESCE(k.cnt, 0)   AS children_count,
                       COALESCE(ka.cnt, 0)  AS active_children
                  FROM quorum_polls p
                  LEFT JOIN seminare s ON s.Seminar_id = p.seminar_id
                  LEFT JOIN (
                        SELECT poll_id, COUNT(*) AS cnt
                          FROM quorum_responses
                         GROUP BY poll_id
                  ) c ON c.poll_id = p.id
                  LEFT JOIN (
                        SELECT parent_poll_id AS pid, COUNT(*) AS cnt
                          FROM quorum_polls
                         WHERE parent_poll_id IS NOT NULL
                         GROUP BY parent_poll_id
                  ) k ON k.pid = p.id
                  LEFT JOIN (
                        SELECT parent_poll_id AS pid, COUNT(*) AS cnt
                          FROM quorum_polls
                         WHERE parent_poll_id IS NOT NULL
                           AND is_active = 1 AND archived_at IS NULL
                           AND (expires_at IS NULL OR expires_at > ?)
                         GROUP BY parent_poll_id
                  ) ka ON ka.pid = p.id
                 WHERE {$ownerWhere}
                   AND p.parent_poll_id IS NULL
                   AND {$viewWhere}
                 ORDER BY is_active DESC, p.mkdate DESC";

        $stmt = \DBManager::get()->prepare($sql);
        // Param order by `?` position in the SQL: now (SELECT is_active),
        // now (active-children subquery), then the owner value (WHERE).
        $stmt->execute([time(), time(), $ownerValue]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $semId      = $row['seminar_id']  ?? null;
            $archivedAt = $row['archived_at'] ?? null;
            $out[] = new PollSummary(
                id:            (string) $row['id'],
                token:         (string) $row['token'],
                seminarId:     ($semId === null || $semId === '') ? null : (string) $semId,
                seminarName:   (string) $row['seminar_name'],
                question:      (string) $row['question'],
                type:          (string) $row['type'],
                isActive:      (bool)   $row['is_active'],
                responseCount: (int)    $row['response_count'],
                mkdate:        (int)    $row['mkdate'],
                archivedAt:    $archivedAt === null ? null : (int) $archivedAt,
                childrenCount: (int)    $row['children_count'],
                quizMode:      (bool)   ($row['quiz_mode'] ?? false),
                activeChildrenCount: (int) ($row['active_children'] ?? 0),
            );
        }
        return $out;
    }

    /**
     * Returns the full compare chain of a root poll: `$rootId` itself plus
     * all follow-up polls with `parent_poll_id = $rootId`.
     *
     * Order: root first, then follow-up rounds by `mkdate` ascending
     * (chronological — oldest follow-up first, newest last). One query with
     * `OR`; the `parent_poll_id_idx` makes the self-lookup cheap (no N+1 even
     * with 5+ rounds).
     *
     * The root's `mkdate` is by construction ≤ the children's `mkdate`
     * (children are created via `restartAsCompare` after the root), so
     * `ORDER BY mkdate ASC` suffices.
     *
     * @return list<Poll>
     */
    public function findCompareChain(string $rootId): array
    {
        $sql = 'SELECT id, token, seminar_id, user_id, question, type, options,
                       is_active, mkdate, chdate, archived_at, parent_poll_id,
                       collection_id, collection_position, expires_at
                  FROM quorum_polls
                 WHERE id = ? OR parent_poll_id = ?
                 ORDER BY mkdate ASC';
        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$rootId, $rootId]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[] = self::rowToPoll($row);
        }
        return $out;
    }

    /**
     * Batch aggregation of response counts for multiple polls.
     *
     * Returns `pollId => [optionId => int]`. Polls without responses appear as
     * an empty sub-array so the frontend need not guess which polls have data.
     * One query with `IN(...)`, no N+1 even with 5+ rounds in the compare chain.
     *
     * Edge case: empty `$pollIds` list → no round trip, immediate `[]`.
     *
     * @param list<string> $pollIds
     * @return array<string, array<string, int>>
     */
    public function aggregateCountsForPolls(array $pollIds): array
    {
        if (count($pollIds) === 0) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pollIds), '?'));
        // PHP-side tally (like aggregateCountsForPoll) — multi-select arrays
        // cannot be portably unfolded in SQL.
        $stmt = \DBManager::get()->prepare(
            "SELECT poll_id, response FROM quorum_responses WHERE poll_id IN ($placeholders)"
        );
        $stmt->execute(array_values($pollIds));

        // Pre-initialize with empty maps so polls without responses are
        // visible in the result.
        $out = [];
        foreach ($pollIds as $id) {
            $out[(string) $id] = [];
        }
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $pollId = (string) ($row['poll_id'] ?? '');
            if ($pollId === '' || !isset($out[$pollId])) continue;
            self::tallySelected($out[$pollId], (string) ($row['response'] ?? ''));
        }
        return $out;
    }

    /**
     * Data basis for blind mode.
     *
     * Returns `true` when at least one follow-up poll with
     * `parent_poll_id = $pollId` is currently active. While that holds, the
     * polls app of the root poll must not show results (students must not be
     * swayed in round 2 by round 1's majority).
     *
     * One query using the index on `parent_poll_id` + `is_active`.
     */
    public function hasActiveDescendant(string $pollId): bool
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT 1 FROM quorum_polls
              WHERE parent_poll_id = ? AND is_active = 1
              LIMIT 1'
        );
        $stmt->execute([$pollId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Lifecycle operations.
     *
     * All methods are owner-agnostic — the controller checks the permission
     * beforehand (`$user->id === $poll->userId`).
     */
    public function setActive(string $pollId, bool $active): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls SET is_active = ?, chdate = ? WHERE id = ?'
        );
        $stmt->execute([$active ? 1 : 0, time(), $pollId]);
    }

    /**
     * Binds a poll to a course (or unbinds it, `null` = course-independent).
     * Used by the course tab's "get survey" flow, which adopts an existing
     * workplace survey into the current course.
     */
    public function setSeminar(string $pollId, ?string $seminarId): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls SET seminar_id = ?, chdate = ? WHERE id = ?'
        );
        $stmt->execute([$seminarId, time(), $pollId]);
    }

    /**
     * Edits the question (always allowed) + optionally the options (only when
     * no responses exist) + course binding.
     *
     * `seminarId === null` is explicitly allowed (global poll). When
     * `$options === null` the options are NOT touched (so edit-with-responses
     * works).
     *
     * @param list<array{id: string, label: string}>|null $options
     */
    /**
     * Sets the result visibility for students (opt-out). Orthogonal to the
     * other fields — changeable at any time, including for finished polls.
     */
    public function setResultsPublic(string $pollId, bool $public): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls SET results_public = ?, chdate = ? WHERE id = ?'
        );
        $stmt->execute([$public ? 1 : 0, time(), $pollId]);
    }

    public function updatePollFields(string $pollId, string $question, ?array $options, ?string $seminarId): void
    {
        if ($options === null) {
            $stmt = \DBManager::get()->prepare(
                'UPDATE quorum_polls
                    SET question = ?, seminar_id = ?, chdate = ?
                  WHERE id = ?'
            );
            $stmt->execute([$question, $seminarId, time(), $pollId]);
        } else {
            // Race protection: option swap + response re-check in ONE
            // transaction, the re-check immediately before the UPDATE. Between
            // the service pre-check (`countResponses === 0`) and here a vote
            // could have arrived that was still validated against the OLD
            // options; replacing the options would then leave that response
            // pointing at a removed option ID (no longer aggregatable → silent
            // vote loss). The inline re-check safely catches any response
            // committed by then and narrows the remaining 0→1 window to the
            // sub-millisecond gap between check and UPDATE.
            $db = \DBManager::get();
            $db->beginTransaction();
            try {
                $lock = $db->prepare(
                    'SELECT COUNT(*) FROM quorum_responses WHERE poll_id = ?'
                );
                $lock->execute([$pollId]);
                if ((int) $lock->fetchColumn() > 0) {
                    $db->rollBack();
                    throw new Exceptions\InvalidResponseException(
                        'Antwort-Optionen können nicht mehr geändert werden, '
                        . 'sobald die Umfrage Antworten erhalten hat.'
                    );
                }
                $stmt = $db->prepare(
                    'UPDATE quorum_polls
                        SET question = ?, options = ?, seminar_id = ?, chdate = ?
                      WHERE id = ?'
                );
                $stmt->execute([
                    $question,
                    json_encode($options, JSON_THROW_ON_ERROR),
                    $seminarId,
                    time(),
                    $pollId,
                ]);
                $db->commit();
            } catch (Exceptions\InvalidResponseException $e) {
                throw $e;
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
    }

    /**
     * Returns the response count for a single poll. Used by the service to
     * decide whether the options may still be changed in the edit dialog
     * (otherwise existing responses become invalid).
     */
    public function countResponses(string $pollId): int
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT COUNT(*) FROM quorum_responses WHERE poll_id = ?'
        );
        $stmt->execute([$pollId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Number of follow-up runs (children) of a poll. The detail page
     * (`PollResults`) only shows the "show comparison" button when there is a
     * comparison chain — same condition as the card menu.
     */
    public function countChildren(string $pollId): int
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT COUNT(*) FROM quorum_polls WHERE parent_poll_id = ?'
        );
        $stmt->execute([$pollId]);
        return (int) $stmt->fetchColumn();
    }

    public function setArchivedAt(string $pollId, ?int $timestamp): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls SET archived_at = ?, chdate = ? WHERE id = ?'
        );
        $stmt->execute([$timestamp, time(), $pollId]);
    }

    /**
     * Hard-deletes the poll including all responses. Children (follow-up
     * rounds in the compare chain) remain, their `parent_poll_id` is set to
     * NULL — they become standalone polls. All in one transaction so a partial
     * failure cannot leave orphaned responses.
     */
    public function deletePollHard(string $pollId): void
    {
        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM quorum_responses WHERE poll_id = ?');
            $stmt->execute([$pollId]);

            $stmt = $db->prepare('UPDATE quorum_polls SET parent_poll_id = NULL WHERE parent_poll_id = ?');
            $stmt->execute([$pollId]);

            $stmt = $db->prepare('DELETE FROM quorum_polls WHERE id = ?');
            $stmt->execute([$pollId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Returns all free-text responses of a poll as a string array.
     * Used by the API endpoint `freitext_responses_action`.
     * No PII — only the `text` value from the response JSON.
     *
     * @return list<string>
     */
    public function findFreitextResponses(string $pollId): array
    {
        $sql  = "SELECT JSON_UNQUOTE(JSON_EXTRACT(response, '$.text')) AS txt"
              . " FROM quorum_responses WHERE poll_id = ?";
        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$pollId]);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $txt) {
            if ($txt !== null && $txt !== '') {
                $out[] = (string) $txt;
            }
        }
        return $out;
    }

    /**
     * Member poll IDs of a collection (root polls) for the leaderboard
     * aggregation across the whole quiz session.
     *
     * @return list<string>
     */
    public function findPollIdsInCollection(string $collectionId): array
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT id FROM quorum_polls
              WHERE collection_id = ? AND parent_poll_id IS NULL
              ORDER BY collection_position ASC'
        );
        $stmt->execute([$collectionId]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Pseudonymous leaderboard: score sum per nickname across the given polls.
     * Opt-in responses only (nickname set); top 25. Returns only nickname +
     * sum — no IDs, no PII.
     *
     * @param  list<string> $pollIds
     * @return list<array{nickname: string, score: int}>
     */
    public function aggregateLeaderboard(array $pollIds): array
    {
        if ($pollIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($pollIds), '?'));
        $stmt  = \DBManager::get()->prepare(
            "SELECT nickname, SUM(score) AS total
               FROM quorum_responses
              WHERE poll_id IN ($marks)
                AND nickname IS NOT NULL AND nickname != ''
                AND score IS NOT NULL
              GROUP BY nickname
              ORDER BY total DESC, nickname ASC
              LIMIT 25"
        );
        $stmt->execute(array_values($pollIds));
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[] = ['nickname' => (string) $row['nickname'], 'score' => (int) $row['total']];
        }
        return $out;
    }

    /**
     * Free-text moderation: a response's poll reference (for the ownership
     * chain in the service) — null when the response does not exist.
     */
    public function findResponsePollId(string $responseId): ?string
    {
        $row = QuorumResponse::find($responseId);
        return $row ? (string) $row->poll_id : null;
    }

    /** Free-text moderation: permanently deletes a single response. */
    public function deleteResponse(string $responseId): void
    {
        $stmt = \DBManager::get()->prepare('DELETE FROM quorum_responses WHERE id = ?');
        $stmt->execute([$responseId]);
    }

    /**
     * Free-text responses WITH response IDs — data basis of the moderation
     * list (owner endpoint). The anonymous variant without IDs remains
     * `findFreitextResponses`.
     *
     * @return list<array{id: string, text: string}>
     */
    public function findFreitextResponsesWithIds(string $pollId): array
    {
        $sql  = "SELECT id, JSON_UNQUOTE(JSON_EXTRACT(response, '$.text')) AS txt"
              . " FROM quorum_responses WHERE poll_id = ?";
        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$pollId]);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $txt = $row['txt'] ?? null;
            if ($txt !== null && $txt !== '') {
                $out[] = ['id' => (string) $row['id'], 'text' => (string) $txt];
            }
        }
        return $out;
    }

    /**
     * Aggregates matrix responses as `{ rowId => { scaleId => count } }`.
     * Loads all response JSONs into PHP and aggregates there — acceptable for
     * survey sizes (< 10k responses). For larger datasets JSON_TABLE
     * (MariaDB ≥ 10.6) would be the more performant alternative.
     *
     * @return array<string, array<string, int>>
     */
    public function aggregateMatrixCountsForPoll(string $pollId): array
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT JSON_EXTRACT(response, \'$.choices\') AS choices'
            . ' FROM quorum_responses WHERE poll_id = ?'
        );
        $stmt->execute([$pollId]);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $json) {
            $choices = json_decode((string) $json, true);
            if (!is_array($choices)) continue;
            foreach ($choices as $rowId => $scaleId) {
                // Defense in depth: count only scalar row/scale tokens. A
                // tampered or nested `choices` JSON (array value, object) would
                // otherwise trigger "Illegal offset" warnings or skew the
                // aggregation. The actual whitelist against the poll options
                // already happens in input validation
                // (`PollsService::validateMatrixPayload`).
                if (!is_scalar($rowId) || !is_scalar($scaleId)) continue;
                $rowKey   = (string) $rowId;
                $scaleKey = (string) $scaleId;
                $out[$rowKey][$scaleKey] = ($out[$rowKey][$scaleKey] ?? 0) + 1;
            }
        }
        return $out;
    }

    public function saveResponse(Response $response): void
    {
        QuorumResponse::build([
            'id'       => $response->id,
            'poll_id'  => $response->pollId,
            'response' => json_encode($response->payload, JSON_THROW_ON_ERROR),
            'mkdate'   => $response->mkdate,
            // Quiz: nickname (opt-in) + server-side score — null outside
            // quiz mode.
            'nickname' => $response->nickname,
            'score'    => $response->score,
        ])->store();
    }

    public function savePoll(Poll $poll): void
    {
        QuorumPoll::build([
            'id'         => $poll->id,
            'token'      => $poll->token,
            // null for global polls — DB column is nullable
            'seminar_id' => $poll->seminarId,
            'user_id'    => $poll->userId,
            'question'   => $poll->question,
            'type'       => $poll->type,
            'options'    => json_encode($poll->options, JSON_THROW_ON_ERROR),
            'is_active'  => $poll->isActive ? 1 : 0,
            'quiz_mode'  => $poll->quizMode ? 1 : 0,
            'results_public' => $poll->resultsPublic ? 1 : 0,
            'mkdate'     => $poll->mkdate,
            'chdate'     => $poll->chdate,
            // Lifecycle and chain/collection fields MUST be written on insert.
            // If they were missing, `parent_poll_id` would always stay NULL and
            // the whole compare chain would be dead, because `restartAsCompare`
            // links the DTO but the insert would drop that link.
            'archived_at'         => $poll->archivedAt,
            'parent_poll_id'      => $poll->parentPollId,
            'collection_id'       => $poll->collectionId,
            'collection_position' => $poll->collectionPosition,
            // Auto-stop timestamp (null = no time limit).
            'expires_at'          => $poll->expiresAt,
        ])->store();
    }

    /**
     * Hydrates a `Poll` DTO from an associative SQL row (used by
     * `findCompareChain` etc. — without a SimpleORM round trip).
     *
     * @param array<string,mixed> $row
     */
    private static function rowToPoll(array $row): Poll
    {
        $options = json_decode(self::rowStr($row, 'options', '[]'), true, 512, JSON_THROW_ON_ERROR);
        return new Poll(
            id:                 self::rowStr($row, 'id', ''),
            token:              self::rowStr($row, 'token', ''),
            seminarId:          self::nullableStr($row['seminar_id'] ?? null),
            userId:             self::rowStr($row, 'user_id', ''),
            question:           self::rowStr($row, 'question', ''),
            type:               self::rowStr($row, 'type', 'mc'),
            options:            is_array($options) ? $options : [],
            isActive:           self::rowBool($row, 'is_active', false),
            mkdate:             self::rowInt($row, 'mkdate'),
            chdate:             self::rowInt($row, 'chdate'),
            archivedAt:         self::nullableInt($row['archived_at'] ?? null),
            parentPollId:       self::nullableStr($row['parent_poll_id'] ?? null),
            collectionId:       self::nullableStr($row['collection_id'] ?? null),
            collectionPosition: self::nullableInt($row['collection_position'] ?? null),
            expiresAt:          self::nullableInt($row['expires_at'] ?? null),
            quizMode:           self::rowBool($row, 'quiz_mode', false),
            resultsPublic:      self::rowBool($row, 'results_public', true),
        );
    }

    /**
     * Typed read helpers for the associative SQL row in `rowToPoll` — they
     * bundle the `(cast) ($row[$key] ?? $default)` idiom in one place so the
     * mapper itself avoids a `??` chain. Behavior 1:1 with the previous inline
     * code.
     *
     * @param array<string,mixed> $row
     */
    private static function rowStr(array $row, string $key, string $default): string
    {
        return (string) ($row[$key] ?? $default);
    }

    /** @param array<string,mixed> $row */
    private static function rowInt(array $row, string $key): int
    {
        return (int) ($row[$key] ?? 0);
    }

    /** @param array<string,mixed> $row */
    private static function rowBool(array $row, string $key, bool $default): bool
    {
        return (bool) ($row[$key] ?? $default);
    }

    private static function toPoll(QuorumPoll $row): Poll
    {
        $options = json_decode((string) $row->options, true, 512, JSON_THROW_ON_ERROR);
        return new Poll(
            id:                 (string) $row->id,
            token:              (string) $row->token,
            // Nullable. Normalize both NULL and empty string to null.
            seminarId:          self::nullableStr($row->seminar_id),
            userId:             (string) $row->user_id,
            question:           (string) $row->question,
            type:               (string) $row->type,
            options:            is_array($options) ? $options : [],
            isActive:           (bool)   $row->is_active,
            mkdate:             (int)    $row->mkdate,
            chdate:             (int)    $row->chdate,
            archivedAt:         self::nullableInt($row->archived_at),
            parentPollId:       self::nullableStr($row->parent_poll_id),
            collectionId:       self::nullableStr($row->collection_id),
            collectionPosition: self::nullableInt($row->collection_position),
            expiresAt:          self::nullableInt($row->expires_at),
            quizMode:           (bool) ($row->quiz_mode ?? false),
            resultsPublic:      (bool) ($row->results_public ?? true),
        );
    }

    /**
     * Normalizes a nullable string field: NULL and empty string both become
     * null, otherwise the trimmed (string) cast. Bundles the
     * `($v === null || $v === '') ? null : (string) $v` idiom used in both
     * mappers (`rowToPoll`/`toPoll`) for seminar_id/parent/collection.
     */
    private static function nullableStr(mixed $value): ?string
    {
        return ($value === null || $value === '') ? null : (string) $value;
    }

    /** Nullable integer field: NULL stays null, otherwise (int) cast. */
    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
