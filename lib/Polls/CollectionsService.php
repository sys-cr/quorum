<?php

declare(strict_types=1);

namespace Quorum\Polls;

use Quorum\Polls\Exceptions\InvalidResponseException;
use Quorum\Polls\Exceptions\PollNotFoundException;
use Random\Randomizer;

/**
 * Domain service for Quorum collections.
 *
 * Lifecycle: createCollection / updateCollection / archiveCollection /
 * unarchiveCollection / deleteCollection (hard delete; member polls become
 * standalone polls).
 *
 * Membership: addPollToCollection / removePollFromCollection / reorderPolls
 * — all transactional at the repository.
 *
 * Owner check is at the controller boundary — the service is owner-agnostic
 * (CLI-capable).
 */
final class CollectionsService
{
    public function __construct(
        private readonly CollectionsRepository $repo,
        private readonly PollsRepository $pollsRepo,
    ) {
    }

    /**
     * @throws InvalidResponseException
     */
    public function createCollection(string $userId, string $name, ?string $description = null, ?string $seminarId = null): Collection
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidResponseException('Sammlungs-Name darf nicht leer sein.');
        }

        $now        = time();
        $collection = new Collection(
            id:          self::generateId(),
            userId:      $userId,
            name:        $name,
            description: $description === null ? null : (trim($description) ?: null),
            mkdate:      $now,
            chdate:      $now,
            archivedAt:  null,
            seminarId:   ($seminarId ?? '') === '' ? null : $seminarId,
        );
        $this->repo->save($collection);
        return $collection;
    }

    /**
     * @throws InvalidResponseException
     * @throws PollNotFoundException
     */
    public function updateCollection(string $collectionId, string $name, ?string $description, ?string $seminarId = null): Collection
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidResponseException('Sammlungs-Name darf nicht leer sein.');
        }
        $existing = $this->repo->findById($collectionId);
        if ($existing === null) {
            throw new PollNotFoundException(sprintf('Sammlung "%s" nicht gefunden.', $collectionId));
        }
        $updated = new Collection(
            id:          $existing->id,
            userId:      $existing->userId,
            name:        $name,
            description: $description === null ? null : (trim($description) ?: null),
            mkdate:      $existing->mkdate,
            chdate:      time(),
            archivedAt:  $existing->archivedAt,
            seminarId:   ($seminarId ?? '') === '' ? null : $seminarId,
        );
        $this->repo->save($updated);
        return $updated;
    }

    /**
     * Sets only the course assignment of a collection (without touching
     * name/description). `null` = course-independent.
     */
    public function setSeminar(string $collectionId, ?string $seminarId): void
    {
        $this->repo->setSeminar($collectionId, ($seminarId ?? '') === '' ? null : $seminarId);
    }

    public function archiveCollection(string $collectionId): void
    {
        $this->setArchived($collectionId, time());
    }

    public function unarchiveCollection(string $collectionId): void
    {
        $this->setArchived($collectionId, null);
    }

    /**
     * Hard-deletes the collection. Member polls are kept — they become
     * standalone polls (`collection_id = NULL`).
     */
    public function deleteCollection(string $collectionId): void
    {
        $this->repo->deleteHard($collectionId);
    }

    /**
     * Starts voting for a collection (collection flow control).
     *
     * Two modes (user flow from live-test feedback):
     *   - `all`  → all member questions active at once; students click
     *              through on their own, the owner finishes later and shows
     *              the results in the presenter.
     *   - `step` → only the FIRST question active, all others inactive; the
     *              owner advances in the presenter via "start next question"
     *              (there: finish current + start next).
     *
     * @throws PollNotFoundException    collection does not exist
     * @throws InvalidResponseException unknown mode / empty collection
     */
    public function startCollection(string $collectionId, string $mode = 'all'): void
    {
        if (!in_array($mode, ['all', 'step'], true)) {
            throw new InvalidResponseException('Unbekannter Start-Modus (erwartet: all|step).');
        }
        if ($this->repo->findById($collectionId) === null) {
            throw new PollNotFoundException(sprintf('Sammlung "%s" nicht gefunden.', $collectionId));
        }
        $polls = $this->repo->findPollsInCollection($collectionId);
        if ($polls === []) {
            throw new InvalidResponseException('Diese Sammlung enthält keine Umfragen.');
        }

        if ($mode === 'all') {
            $this->repo->setMembersActive($collectionId, true);
            return;
        }
        // step: everything off, then only the first question (lowest position) on.
        $this->repo->setMembersActive($collectionId, false);
        $this->pollsRepo->setActive((string) $polls[0]['id'], true);
    }

    /**
     * Stops voting for ALL member questions (counterpart of
     * `startCollection`, both modes).
     *
     * @throws PollNotFoundException
     */
    public function finishCollection(string $collectionId): void
    {
        if ($this->repo->findById($collectionId) === null) {
            throw new PollNotFoundException(sprintf('Sammlung "%s" nicht gefunden.', $collectionId));
        }
        $this->repo->setMembersActive($collectionId, false);
    }

    /**
     * Adds a poll at the end of the collection (or at `position`). Any
     * existing membership in another collection is replaced (a poll is always
     * in 0 or 1 collection).
     *
     * @throws PollNotFoundException
     */
    public function addPollToCollection(string $collectionId, string $pollId, string $ownerUserId, ?int $position = null): void
    {
        // Owner check in the service (defense-in-depth): the poll AND the
        // target collection must belong to the same person. Prevents IDOR even
        // if a future caller forgets the controller-side check. Foreign IDs
        // surface as "not found" (404), not 403 (no leak whether the ID exists).
        $poll = $this->pollsRepo->findById($pollId);
        if ($poll === null || $poll->userId !== $ownerUserId) {
            throw new PollNotFoundException(sprintf('Poll "%s" nicht gefunden.', $pollId));
        }
        $collection = $this->repo->findById($collectionId);
        if ($collection === null || $collection->userId !== $ownerUserId) {
            throw new PollNotFoundException(sprintf('Sammlung "%s" nicht gefunden.', $collectionId));
        }
        $pos = $position ?? $this->repo->nextPositionFor($collectionId);
        $this->repo->setPollMembership($pollId, $collectionId, $pos);
    }

    public function removePollFromCollection(string $pollId): void
    {
        $this->repo->setPollMembership($pollId, null, null);
    }

    /**
     * Sets a new order for the polls in a collection. Polls not in the
     * collection are ignored (repository behavior).
     *
     * @param list<string> $orderedPollIds
     */
    public function reorderPolls(string $collectionId, array $orderedPollIds): void
    {
        $this->repo->reorder($collectionId, $orderedPollIds);
    }

    /**
     * @return list<array{id: string, token: string, question: string,
     *                    type: string, options: array, is_active: bool,
     *                    position: int, response_count: int}>
     */
    public function findPollsInCollection(string $collectionId): array
    {
        return $this->repo->findPollsInCollection($collectionId);
    }

    public function findCollectionById(string $collectionId): ?Collection
    {
        return $this->repo->findById($collectionId);
    }

    /**
     * @return list<CollectionSummary>
     */
    public function findSummariesByUser(string $userId, string $view = 'active'): array
    {
        return $this->repo->findSummariesByUser($userId, $view);
    }

    /**
     * Collections of one course (course tab, co-teaching).
     *
     * @return list<CollectionSummary>
     */
    public function findSummariesBySeminar(string $seminarId, string $view = 'active'): array
    {
        return $this->repo->findSummariesBySeminar($seminarId, $view);
    }

    /**
     * Student view: running & released collections of one course with their
     * member polls.
     *
     * @return list<array{id:string,name:string,description:?string,
     *                    active:list<array<string,string>>,finished:list<array<string,string>>}>
     */
    public function findStudentCourseCollections(string $seminarId): array
    {
        return $this->repo->findStudentCourseCollections($seminarId);
    }

    private function setArchived(string $collectionId, ?int $timestamp): void
    {
        $existing = $this->repo->findById($collectionId);
        if ($existing === null) {
            throw new PollNotFoundException(sprintf('Sammlung "%s" nicht gefunden.', $collectionId));
        }
        $updated = new Collection(
            id:          $existing->id,
            userId:      $existing->userId,
            name:        $existing->name,
            description: $existing->description,
            mkdate:      $existing->mkdate,
            chdate:      time(),
            archivedAt:  $timestamp,
            // Do not lose the course assignment when archiving/reactivating.
            seminarId:   $existing->seminarId,
        );
        $this->repo->save($updated);
    }

    private static function generateId(): string
    {
        return bin2hex((new Randomizer())->getBytes(16));
    }
}
