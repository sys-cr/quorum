<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Persistence adapter for Quorum collections.
 *
 * Owner filtering happens at the service/controller boundary — all methods
 * here are owner-agnostic and rely on the caller having read `userId` from
 * the session.
 */
class CollectionsRepository
{
    public function findById(string $id): ?Collection
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT id, user_id, seminar_id, name, description, mkdate, chdate, archived_at
               FROM quorum_poll_collections WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? self::toCollection($row) : null;
    }

    /**
     * Lists a user's collections with the aggregated member-poll count
     * in a single query via `LEFT JOIN COUNT(*)`.
     *
     * @return list<CollectionSummary>
     */
    public function findSummariesByUser(string $userId, string $view = 'active'): array
    {
        return $this->findSummariesWhere('c.user_id = ?', $userId, $view);
    }

    /**
     * Collections of ONE course (course tab, co-teaching) with the aggregated
     * member-poll count. Owner-agnostic — filtered via the course assignment
     * `c.seminar_id`, not via the owner.
     *
     * @return list<CollectionSummary>
     */
    public function findSummariesBySeminar(string $seminarId, string $view = 'active'): array
    {
        return $this->findSummariesWhere('c.seminar_id = ?', $seminarId, $view);
    }

    /**
     * Shared summary query (one query with `LEFT JOIN seminare` for the
     * display name + `LEFT JOIN COUNT(*)` for the members).
     *
     * @return list<CollectionSummary>
     */
    private function findSummariesWhere(string $scopeClause, string $param, string $view): array
    {
        $where = match ($view) {
            'archive' => 'c.archived_at IS NOT NULL',
            'all'     => '1=1',
            default   => 'c.archived_at IS NULL',
        };

        $sql = "SELECT c.id, c.name, c.description, c.mkdate, c.archived_at,
                       c.seminar_id,
                       COALESCE(s.Name, '') AS seminar_name,
                       COALESCE(p.cnt, 0) AS poll_count,
                       COALESCE(p.active_cnt, 0) AS active_count
                  FROM quorum_poll_collections c
                  LEFT JOIN seminare s ON s.Seminar_id = c.seminar_id
                  LEFT JOIN (
                        SELECT collection_id, COUNT(*) AS cnt,
                               SUM(is_active = 1) AS active_cnt
                          FROM quorum_polls
                         WHERE collection_id IS NOT NULL
                           -- Nur Wurzel-Polls zählen, konsistent zu
                           -- findPollsInCollection (das Compare-Children via
                           -- `parent_poll_id IS NULL` ausschließt). Sonst meldet
                           -- die Sammlung mehr Umfragen, als die Liste zeigt.
                           AND parent_poll_id IS NULL
                         GROUP BY collection_id
                  ) p ON p.collection_id = c.id
                 WHERE $scopeClause
                   AND $where
                 ORDER BY c.mkdate DESC";

        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$param]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $seminarId = ($row['seminar_id'] ?? null) === null ? null : (string) $row['seminar_id'];
            $seminarName = (string) ($row['seminar_name'] ?? '');
            $out[] = new CollectionSummary(
                id:          (string) $row['id'],
                name:        (string) $row['name'],
                description: $row['description'] === null ? null : (string) $row['description'],
                mkdate:      (int)    $row['mkdate'],
                archivedAt:  $row['archived_at'] === null ? null : (int) $row['archived_at'],
                pollCount:   (int)    $row['poll_count'],
                activeCount: (int)    $row['active_count'],
                seminarId:   $seminarId,
                seminarName: ($seminarId !== null && $seminarName !== '') ? $seminarName : null,
            );
        }
        return $out;
    }

    public function save(Collection $collection): void
    {
        $stmt = \DBManager::get()->prepare(
            'INSERT INTO quorum_poll_collections
                (id, user_id, seminar_id, name, description, mkdate, chdate, archived_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                seminar_id = VALUES(seminar_id),
                name = VALUES(name),
                description = VALUES(description),
                chdate = VALUES(chdate),
                archived_at = VALUES(archived_at)'
        );
        $stmt->execute([
            $collection->id,
            $collection->userId,
            $collection->seminarId,
            $collection->name,
            $collection->description,
            $collection->mkdate,
            $collection->chdate,
            $collection->archivedAt,
        ]);
    }

    /**
     * Sets the (optional) course binding of a collection.
     * `seminarId === null` makes the collection course-independent.
     */
    public function setSeminar(string $collectionId, ?string $seminarId): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_poll_collections SET seminar_id = ?, chdate = ? WHERE id = ?'
        );
        $stmt->execute([$seminarId, time(), $collectionId]);
    }

    /**
     * Hard-deletes the collection. Member polls are NOT deleted; their
     * `collection_id` and `collection_position` are set to NULL so they
     * remain as standalone polls in the workplace. All in one transaction
     * to avoid orphaned membership state.
     */
    public function deleteHard(string $collectionId): void
    {
        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE quorum_polls
                    SET collection_id = NULL, collection_position = NULL
                  WHERE collection_id = ?'
            );
            $stmt->execute([$collectionId]);

            $stmt = $db->prepare('DELETE FROM quorum_poll_collections WHERE id = ?');
            $stmt->execute([$collectionId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Sets a poll's `collection_id` and `collection_position`. With
     * `$collectionId === null` the poll is removed from any collection.
     * Position = NULL is allowed (standalone polls).
     */
    public function setPollMembership(string $pollId, ?string $collectionId, ?int $position): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls
                SET collection_id = ?, collection_position = ?, chdate = ?
              WHERE id = ?'
        );
        $stmt->execute([$collectionId, $position, time(), $pollId]);
    }

    /**
     * Returns all polls in a collection ordered by `collection_position`.
     * Fetches only the columns the presenter needs rather than reusing the
     * full `PollsRepository` mapping.
     *
     * @return list<array{id: string, token: string, question: string,
     *                    type: string, options: array, is_active: bool,
     *                    position: int, response_count: int}>
     */
    public function findPollsInCollection(string $collectionId): array
    {
        $sql = "SELECT p.id, p.token, p.question, p.type, p.options,
                       p.is_active, p.collection_position AS position,
                       COALESCE(c.cnt, 0) AS response_count
                  FROM quorum_polls p
                  LEFT JOIN (
                        SELECT poll_id, COUNT(*) AS cnt
                          FROM quorum_responses
                         GROUP BY poll_id
                  ) c ON c.poll_id = p.id
                 WHERE p.collection_id = ?
                   AND p.parent_poll_id IS NULL
                 ORDER BY p.collection_position ASC, p.mkdate ASC";
        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$collectionId]);

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $options = json_decode((string) $row['options'], true, 512, JSON_THROW_ON_ERROR);
            $out[] = [
                'id'             => (string) $row['id'],
                'token'          => (string) $row['token'],
                'question'       => (string) $row['question'],
                'type'           => (string) $row['type'],
                'options'        => is_array($options) ? $options : [],
                'is_active'      => (bool)   $row['is_active'],
                'position'       => (int)    $row['position'],
                'response_count' => (int)    $row['response_count'],
            ];
        }
        return $out;
    }

    /**
     * Toggles `is_active` for ALL root member polls of a collection in one
     * statement (collection flow control — start "all questions" or finish
     * the collection). Compare children stay untouched (`parent_poll_id IS
     * NULL`, consistent with findPollsInCollection).
     */
    public function setMembersActive(string $collectionId, bool $active): void
    {
        $stmt = \DBManager::get()->prepare(
            'UPDATE quorum_polls
                SET is_active = ?, chdate = ?
              WHERE collection_id = ?
                AND parent_poll_id IS NULL'
        );
        $stmt->execute([$active ? 1 : 0, time(), $collectionId]);
    }

    /**
     * Computes the next free position (current `MAX + 1`) in a collection.
     * Returns 0 when the collection is still empty.
     */
    public function nextPositionFor(string $collectionId): int
    {
        // Root polls only (consistent with findPollsInCollection /
        // findSummariesByUser) — compare children inherit the original's
        // collection_position and would otherwise skew the MAX.
        $stmt = \DBManager::get()->prepare(
            'SELECT COALESCE(MAX(collection_position), -1) + 1
               FROM quorum_polls
              WHERE collection_id = ?
                AND parent_poll_id IS NULL'
        );
        $stmt->execute([$collectionId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Assigns `collection_position` values for an ordered list of poll IDs
     * in one transaction. Polls not in the collection are ignored.
     *
     * @param list<string> $orderedPollIds
     */
    public function reorder(string $collectionId, array $orderedPollIds): void
    {
        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE quorum_polls
                    SET collection_position = ?, chdate = ?
                  WHERE id = ? AND collection_id = ?'
            );
            $now = time();
            foreach ($orderedPollIds as $i => $pollId) {
                $stmt->execute([$i, $now, $pollId, $collectionId]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Student view: running & released collections of ONE course. A single
     * grouping query over `collection_id` (NOT over the member polls'
     * `seminar_id`, which may differ). Per collection:
     *   - `active`:   currently running root member polls (anonymous join token)
     *   - `finished`: finished root member polls with `results_public = 1`
     * Collections without visible members (neither active nor released) are
     * dropped. Anonymity: only aggregate-capable fields, no personal reference.
     *
     * @return list<array{id:string,name:string,description:?string,
     *                    active:list<array{id:string,token:string,question:string,type:string}>,
     *                    finished:list<array{id:string,question:string,type:string}>}>
     */
    public function findStudentCourseCollections(string $seminarId): array
    {
        $now = time();
        $sql = "SELECT c.id AS collection_id, c.name, c.description, c.mkdate,
                       p.id AS poll_id, p.token, p.question, p.type,
                       (p.is_active = 1 AND p.archived_at IS NULL
                            AND (p.expires_at IS NULL OR p.expires_at > ?)) AS is_running,
                       p.results_public
                  FROM quorum_poll_collections c
                  JOIN quorum_polls p
                        ON p.collection_id = c.id AND p.parent_poll_id IS NULL
                 WHERE c.seminar_id = ?
                   AND c.archived_at IS NULL
                 ORDER BY c.mkdate DESC, p.collection_position ASC, p.mkdate ASC";
        $stmt = \DBManager::get()->prepare($sql);
        $stmt->execute([$now, $seminarId]);

        $byCollection = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $cid = (string) $row['collection_id'];
            if (!isset($byCollection[$cid])) {
                $byCollection[$cid] = [
                    'id'          => $cid,
                    'name'        => (string) $row['name'],
                    'description' => $row['description'] === null ? null : (string) $row['description'],
                    'active'      => [],
                    'finished'    => [],
                ];
            }
            if ((int) $row['is_running'] === 1) {
                $byCollection[$cid]['active'][] = [
                    'id'       => (string) $row['poll_id'],
                    'token'    => (string) $row['token'],
                    'question' => (string) $row['question'],
                    'type'     => (string) $row['type'],
                ];
            } elseif ((int) $row['results_public'] === 1) {
                // Finished + released → result review per member poll.
                $byCollection[$cid]['finished'][] = [
                    'id'       => (string) $row['poll_id'],
                    'question' => (string) $row['question'],
                    'type'     => (string) $row['type'],
                ];
            }
        }

        // Filter out collections without visible members.
        return array_values(array_filter(
            $byCollection,
            static fn (array $c): bool => $c['active'] !== [] || $c['finished'] !== [],
        ));
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function toCollection(array $row): Collection
    {
        return new Collection(
            id:          (string) $row['id'],
            userId:      (string) $row['user_id'],
            name:        (string) $row['name'],
            description: $row['description'] === null ? null : (string) $row['description'],
            mkdate:      (int)    $row['mkdate'],
            chdate:      (int)    $row['chdate'],
            archivedAt:  $row['archived_at'] === null ? null : (int) $row['archived_at'],
            seminarId:   ($row['seminar_id'] ?? null) === null ? null : (string) $row['seminar_id'],
        );
    }
}
