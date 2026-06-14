<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Read-only DTO for the collection list in the workplace.
 *
 * Shows core data + aggregated `pollCount` (number of member polls).
 * Populated via `CollectionsRepository::findSummariesByUser` in one query
 * with `LEFT JOIN COUNT(*)` — no N+1.
 */
final class CollectionSummary
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $name,
        public readonly ?string $description,
        public readonly int     $mkdate,
        public readonly ?int    $archivedAt,
        public readonly int     $pollCount,
        // Number of currently ACTIVE member polls — drives the lifecycle
        // actions (start vs. finish) and the "running" badge in the frontend.
        public readonly int     $activeCount = 0,
        // Optional course assignment (seminar) + display name.
        public readonly ?string $seminarId   = null,
        public readonly ?string $seminarName = null,
    ) {
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'mkdate'       => $this->mkdate,
            'archived_at'  => $this->archivedAt,
            'poll_count'   => $this->pollCount,
            'active_count' => $this->activeCount,
            'seminar_id'   => $this->seminarId,
            'seminar_name' => $this->seminarName,
        ];
    }
}
