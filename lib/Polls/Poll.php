<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Domain DTO for a poll question. Hydrated by the repository and passed on
 * by the service as a read-only value.
 *
 * `options` is polymorphic per question type: a list of options (choice types),
 * a `{rows, scale}` object for matrix questions, empty for free text. Options
 * may optionally carry `correct` (quiz scoring).
 *
 * @phpstan-type PollOption array{id: string, label?: string, correct?: bool}
 * @phpstan-type PollOptions list<PollOption>|array{rows: list<PollOption>, scale: list<PollOption>}
 */
final class Poll
{
    /**
     * @param PollOptions $options
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $token,
        /** Optional: global polls (no course binding) are allowed. */
        public readonly ?string $seminarId,
        public readonly string  $userId,
        public readonly string  $question,
        public readonly string  $type,
        public readonly array   $options,
        public readonly bool    $isActive,
        public readonly int     $mkdate,
        public readonly int     $chdate,
        /** Soft-delete timestamp; null = visible in active list, int = archived. */
        public readonly ?int    $archivedAt   = null,
        /** Reference to the original poll if this is a compare follow-up round. */
        public readonly ?string $parentPollId = null,
        /** Membership in a Quorum collection (0..1 collection per poll). */
        public readonly ?string $collectionId       = null,
        public readonly ?int    $collectionPosition = null,
        /**
         * Set when this poll is the root of a compare chain and at least one
         * follow-up poll is currently running. The polls app then renders NO
         * round-1 results so students do not let their discussion be guided by
         * the majority opinion. Default `false` — the service layer sets the
         * flag per request via `withBlindMode()`.
         */
        public readonly bool    $blindMode          = false,
        /**
         * Optional auto-stop timestamp (Unix seconds). `null` = no time limit.
         * Once elapsed the poll counts as finished — server-side enforcement
         * rejects late responses.
         */
        public readonly ?int    $expiresAt          = null,
        /**
         * Quiz mode (opt-in, defaults to off). Only meaningful for
         * single-choice questions with at least one option marked correct —
         * the service then computes points server-side.
         */
        public readonly bool    $quizMode           = false,
        /**
         * Result visibility for students in the course tab (opt-out, defaults
         * to on/public). Teachers can switch it off per poll; the finished poll
         * then does NOT appear in the student view. Affects only the result
         * review — participation in a running poll (anonymous, via link/QR)
         * remains unaffected.
         */
        public readonly bool    $resultsPublic      = true,
    ) {
    }

    /**
     * `true` when a time limit is set and has been exceeded. The server
     * clock (`$now`) is authoritative, never the client.
     */
    public function isExpired(int $now): bool
    {
        return $this->expiresAt !== null && $now >= $this->expiresAt;
    }

    /**
     * Effective active state: flagged active AND not expired.
     * Single source of truth for "voting is still allowed".
     */
    public function isEffectivelyActive(int $now): bool
    {
        return $this->isActive && !$this->isExpired($now);
    }

    /**
     * Remaining seconds until auto-stop, or `null` without a time limit.
     * Never negative (min. 0).
     */
    public function remainingSeconds(int $now): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }
        return max(0, $this->expiresAt - $now);
    }

    /**
     * Returns a copy of this poll with the blind-mode flag set.
     * Immutable value-object pattern — the original stays unchanged.
     */
    public function withBlindMode(bool $blindMode): self
    {
        return new self(
            id:                 $this->id,
            token:              $this->token,
            seminarId:          $this->seminarId,
            userId:             $this->userId,
            question:           $this->question,
            type:               $this->type,
            options:            $this->options,
            isActive:           $this->isActive,
            mkdate:             $this->mkdate,
            chdate:             $this->chdate,
            archivedAt:         $this->archivedAt,
            parentPollId:       $this->parentPollId,
            collectionId:       $this->collectionId,
            collectionPosition: $this->collectionPosition,
            blindMode:          $blindMode,
            expiresAt:          $this->expiresAt,
            quizMode:           $this->quizMode,
            resultsPublic:      $this->resultsPublic,
        );
    }

    /**
     * Options for the anonymous participant API — WITHOUT `correct` flags, so
     * the right answer cannot be read from the network tab (decisive in quiz
     * mode, a spoiler otherwise). Matrix options (`{rows, scale}`) carry no
     * flags and stay raw.
     *
     * @return array<mixed>
     */
    public function optionsForParticipants(): array
    {
        if (!array_is_list($this->options)) {
            return $this->options;
        }
        return array_map(
            static function (array $opt): array {
                unset($opt['correct']);
                return $opt;
            },
            $this->options,
        );
    }

    /** Three states derived orthogonally from is_active + archived_at. */
    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function isPaused(): bool
    {
        return !$this->isActive && !$this->isArchived();
    }

    public function hasOption(string $optionId): bool
    {
        foreach ($this->options as $opt) {
            if ($opt['id'] === $optionId) {
                return true;
            }
        }
        return false;
    }

    /**
     * `true` when the option is marked correct (`correct: true` in the
     * options schema). Basis for quiz scoring.
     */
    public function isCorrectOption(string $optionId): bool
    {
        foreach ($this->options as $opt) {
            if (($opt['id'] ?? null) === $optionId) {
                return (bool) ($opt['correct'] ?? false);
            }
        }
        return false;
    }

    /** `true` when at least one option is marked correct (quiz-capable). */
    public function hasCorrectAnswers(): bool
    {
        foreach ($this->options as $opt) {
            if (!empty($opt['correct'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * IDs of the options marked correct — basis for the learning effect
     * (correct answer + own correctness). Delivered to participants EXCLUSIVELY
     * for already ended polls, never while a question is running.
     *
     * @return list<string>
     */
    public function correctOptionIds(): array
    {
        $ids = [];
        foreach ($this->options as $opt) {
            if (!empty($opt['correct']) && isset($opt['id'])) {
                $ids[] = (string) $opt['id'];
            }
        }
        return $ids;
    }

    /**
     * @return array<string,mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id'         => $this->id,
            'token'      => $this->token,
            'question'   => $this->question,
            'type'       => $this->type,
            'options'    => $this->options,
            // Client-visible but server-enforced: while `blind_mode = true`
            // the polls app delivers no round-1 counts.
            'blind_mode' => $this->blindMode,
            // Absolute auto-stop timestamp (Unix seconds) or null. The client
            // computes the countdown from `expires_at` − server `now`
            // (the controller supplies `now`/`remaining_seconds`).
            'expires_at' => $this->expiresAt,
            // The polls-app uses this to show the nickname opt-in and the
            // leaderboard. Only the flag — correct markers stay server-side
            // (no leak of the right answer to participants).
            'quiz_mode'  => $this->quizMode,
            // Result visibility for students (opt-out) — the teacher UI uses
            // this to show the switch state.
            'results_public' => $this->resultsPublic,
        ];
    }
}
