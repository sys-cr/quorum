<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Domain DTO for a poll collection.
 *
 * A collection belongs to an owner (`userId`), carries a plaintext name and
 * an optional description. Lifecycle analogous to Poll: `archivedAt` puts
 * the collection into the archive state (soft delete); hard delete goes
 * through the service.
 *
 * Member polls are not held on the DTO directly — the repository provides
 * them on demand via `findPollsByCollection($id)`.
 */
final class Collection
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $userId,
        public readonly string  $name,
        public readonly ?string $description,
        public readonly int     $mkdate,
        public readonly int     $chdate,
        public readonly ?int    $archivedAt = null,
        /**
         * Optional course assignment (seminar). INDEPENDENT of the member
         * polls' `seminarId` values — a collection can be assigned to a
         * course while its questions are course-independent or hang in other
         * courses. `null` = course-independent collection.
         */
        public readonly ?string $seminarId = null,
    ) {
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'user_id'     => $this->userId,
            'name'        => $this->name,
            'description' => $this->description,
            'mkdate'      => $this->mkdate,
            'chdate'      => $this->chdate,
            'archived_at' => $this->archivedAt,
            'seminar_id'  => $this->seminarId,
        ];
    }
}
