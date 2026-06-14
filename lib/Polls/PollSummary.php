<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Read-only DTO for the workplace widget list.
 *
 * Narrow variant of `Poll`: additionally carries `seminarName` and the
 * aggregated `responseCount`, and omits answer options (the widget only
 * shows a question preview, no answers — clicking opens the course detail view).
 *
 * Populated via `PollsRepository::findSummariesByUser()` in **one** query
 * with a seminar JOIN + `LEFT JOIN ... COUNT(*) GROUP BY` (no N+1, one
 * SELECT per widget call).
 */
final class PollSummary
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $token,
        /** Optional: global polls (no course binding) are allowed. */
        public readonly ?string $seminarId,
        /** Empty for global polls. */
        public readonly string  $seminarName,
        public readonly string  $question,
        public readonly string  $type,
        public readonly bool    $isActive,
        public readonly int     $responseCount,
        public readonly int     $mkdate,
        /** Soft delete; null = active list, int = archive. */
        public readonly ?int    $archivedAt    = null,
        /** Number of follow-up rounds in the compare chain (badge). */
        public readonly int     $childrenCount = 0,
        /** Quiz mode active — for the quiz badge on the card. */
        public readonly bool    $quizMode      = false,
        /** Number of currently running follow-up rounds — "compare round running" hint. */
        public readonly int     $activeChildrenCount = 0,
    ) {
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toApiArray(): array
    {
        return [
            'id'             => $this->id,
            'token'          => $this->token,
            'seminar_id'     => $this->seminarId,
            'seminar_name'   => $this->seminarName,
            'question'       => $this->question,
            'type'           => $this->type,
            'is_active'      => $this->isActive,
            'response_count' => $this->responseCount,
            'mkdate'         => $this->mkdate,
            'archived_at'    => $this->archivedAt,
            'children_count' => $this->childrenCount,
            'quiz_mode'      => $this->quizMode,
            'active_children' => $this->activeChildrenCount,
        ];
    }
}
