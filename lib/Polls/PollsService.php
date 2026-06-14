<?php

declare(strict_types=1);

namespace Quorum\Polls;

use Quorum\Moderation\TextModerator;
use Quorum\Polls\Exceptions\InvalidResponseException;
use Quorum\Polls\Exceptions\PollInactiveException;
use Quorum\Polls\Exceptions\PollNotFoundException;
use Random\Randomizer;

/**
 * Domain service for polls. Encapsulates:
 *   - Token resolution with active-state check
 *   - Response validation against the question's options
 *
 * Tests mock `PollsRepository` — the service has no direct DB calls.
 */
final class PollsService
{
    /** Upper bounds on creation — protect aggregation and export from
     *  absurdly large definitions (resource protection, not a UX limit). */
    private const MAX_LIST_OPTIONS = 50;
    private const MAX_MATRIX_ROWS  = 50;
    private const MAX_MATRIX_SCALE = 20;

    public function __construct(
        private readonly PollsRepository $repo,
        /** Optional free-text blocklist moderation. null = disabled. */
        private readonly ?TextModerator $moderator = null,
    ) {
    }

    /**
     * @throws PollNotFoundException
     * @throws PollInactiveException
     */
    public function findActivePollByToken(string $token): Poll
    {
        $poll = $this->repo->findByToken($token);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll mit Token "%s" nicht gefunden.', $token));
        }
        // Expired polls count as finished (server clock). Lazy auto-stop
        // if still flagged active.
        $now = time();
        if (!$poll->isEffectivelyActive($now)) {
            if ($poll->isActive && $poll->isExpired($now)) {
                $this->repo->setActive($poll->id, false);
            }
            throw new PollInactiveException(sprintf('Poll "%s" ist nicht aktiv.', $token));
        }
        return $poll;
    }

    /**
     * Loads a poll by ID without checking active state — used by the
     * `LiveResultsStreamer` for auth checks (finished polls may still
     * stream their counts).
     */
    public function findPollById(string $pollId): ?Poll
    {
        return $this->repo->findById($pollId);
    }

    /**
     * Participant live sync: lightweight status view for the anonymous
     * polls-app, which polls it at an interval.
     *
     *   - 'active' → this question is running, `active_token` = own token
     *   - 'paused' → stopped/not yet started (waiting state)
     *   - 'ended'  → archived (finished for good)
     *
     * For 'paused'/'ended', `active_token` points to the currently active
     * sibling question of the same collection (auto-follow without a new QR
     * scan) or is null. At most 2 queries.
     *
     * @return array{status: string, active_token: ?string}
     *
     * @throws PollNotFoundException
     */
    public function statusForToken(string $token): array
    {
        $poll = $this->repo->findByToken($token);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll mit Token "%s" nicht gefunden.', $token));
        }

        $now = time();
        if ($poll->isEffectivelyActive($now)) {
            return ['status' => 'active', 'active_token' => $poll->token];
        }

        $activeToken = null;
        if ($poll->collectionId !== null) {
            $sibling = $this->repo->findActivePollInCollection($poll->collectionId, $poll->id);
            // Defensive double-check of the server clock — the SQL already
            // filters `expires_at`, but the domain rule stays here.
            if ($sibling !== null && $sibling->isEffectivelyActive($now)) {
                $activeToken = $sibling->token;
            }
        }

        // Auto-follow within a compare chain: if someone scanned the QR of the
        // first round and the lecturer starts a follow-up round via "Restart ·
        // Compare", the phone moves there automatically (like with collections).
        // Only kicks in when a collection has not already been followed.
        if ($activeToken === null) {
            $rootId = $this->resolveChainRoot($poll);
            $active = $this->repo->findActivePollInChain($rootId);
            if ($active !== null && $active->id !== $poll->id && $active->isEffectivelyActive($now)) {
                $activeToken = $active->token;
            }
        }

        return [
            'status'       => $poll->isArchived() ? 'ended' : 'paused',
            'active_token' => $activeToken,
        ];
    }

    /**
     * Free-text moderation (post-moderation): removes a single free-text
     * response. The blocklist filters up front; here lecturers clean up
     * inappropriate content after the fact. Ownership chain
     * response → poll → owner; foreign/unknown IDs and non-free-text polls
     * surface as "not found" (no leak of whether the ID exists).
     *
     * @throws PollNotFoundException
     */
    public function deleteFreitextResponse(string $responseId, string $ownerUserId): void
    {
        $pollId = $this->repo->findResponsePollId($responseId);
        $poll   = $pollId !== null ? $this->repo->findById($pollId) : null;
        if ($poll === null || $poll->userId !== $ownerUserId || $poll->type !== PollType::FREITEXT) {
            throw new PollNotFoundException(sprintf('Antwort "%s" nicht gefunden.', $responseId));
        }
        $this->repo->deleteResponse($responseId);
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws PollNotFoundException
     * @throws PollInactiveException
     * @throws InvalidResponseException
     */
    public function recordResponse(string $pollId, array $payload): Response
    {
        $poll = $this->repo->findById($pollId);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll-ID "%s" nicht gefunden.', $pollId));
        }
        $now = time();
        // Server-side time-window enforcement. Late responses are rejected;
        // if the poll is expired but still flagged active, the auto-stop is
        // persisted lazily so other paths also see it as finished.
        if (!$poll->isEffectivelyActive($now)) {
            if ($poll->isActive && $poll->isExpired($now)) {
                $this->repo->setActive($poll->id, false);
            }
            throw new PollInactiveException('Diese Abstimmung ist beendet.');
        }

        self::validatePayload($poll, $payload);

        // Server-side free-text blocklist moderation before saving.
        // Blocked submissions are rejected.
        if ($poll->type === PollType::FREITEXT && $this->moderator !== null
            && $this->moderator->isBlocked((string) ($payload['text'] ?? ''))) {
            throw new InvalidResponseException('Ihr Beitrag enthält unzulässige Inhalte und wurde abgelehnt.');
        }

        // Quiz mode: points are computed exclusively server-side (no client
        // trust). The nickname is an active opt-in (empty = the answer counts
        // but does not appear on the leaderboard) and length-limited — no real
        // name, not mandatory. The `nickname` key is removed from the payload
        // so it does not also end up raw in the response.
        $nickname = null;
        $score    = null;
        if ($poll->quizMode) {
            $raw = trim((string) ($payload['nickname'] ?? ''));
            if ($raw !== '') {
                $nickname = mb_substr($raw, 0, 40);
            }
            $selected = $payload['selected'] ?? null;
            $correct  = is_string($selected) && $poll->isCorrectOption($selected);
            // Speed from the time window: total time = expires_at − creation,
            // remaining time = expires_at − now. Without a timer only
            // correctness counts (full points).
            $total     = $poll->expiresAt !== null ? max(0, $poll->expiresAt - $poll->mkdate) : null;
            $remaining = $poll->expiresAt !== null ? max(0, $poll->expiresAt - $now) : null;
            $score     = (new \Quorum\Quiz\QuizScorer())->score($correct, $remaining, $total);
        }
        unset($payload['nickname']);

        $response = new Response(
            id:       self::generateId(),
            pollId:   $poll->id,
            payload:  $payload,
            mkdate:   time(),
            nickname: $nickname,
            score:    $score,
        );
        $this->repo->saveResponse($response);

        return $response;
    }

    /**
     * Pseudonymous leaderboard for the anonymous vote page and the presenter.
     * If the question belongs to a collection, the sum is taken over all
     * member questions (quiz across the whole session), otherwise over this
     * question only. Only nicknames + points leave the server (no real name,
     * no IDs).
     *
     * @return array{scope: string, entries: list<array{rank: int, nickname: string, score: int}>}
     *
     * @throws PollNotFoundException
     */
    /**
     * Quiz solution for participants (learning effect): the options marked
     * correct + the options (without flags). Returns `null` when this is not a
     * quiz OR the question is still running — the correct answer NEVER leaves
     * the server while a question is active (otherwise leaderboard cheat). Only
     * after the end.
     *
     * @return array{correct: list<string>, options: array<mixed>}|null
     */
    public function quizSolutionForToken(string $token): ?array
    {
        $poll = $this->repo->findByToken($token);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll mit Token "%s" nicht gefunden.', $token));
        }
        if (!$poll->quizMode || $poll->isEffectivelyActive(time())) {
            return null;
        }
        return [
            'correct' => $poll->correctOptionIds(),
            'options' => $poll->optionsForParticipants(),
        ];
    }

    public function leaderboardForToken(string $token): array
    {
        $poll = $this->repo->findByToken($token);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll mit Token "%s" nicht gefunden.', $token));
        }

        $scope   = $poll->collectionId !== null ? 'collection' : 'poll';
        $pollIds = $poll->collectionId !== null
            ? $this->repo->findPollIdsInCollection($poll->collectionId)
            : [$poll->id];

        $entries  = [];
        $rank     = 0;
        $position = 0;
        $previous = null;
        foreach ($this->repo->aggregateLeaderboard($pollIds) as $row) {
            $position++;
            // Competition ranking: equal scores share the rank.
            if ($previous === null || $row['score'] < $previous) {
                $rank = $position;
            }
            $previous  = $row['score'];
            $entries[] = [
                'rank'     => $rank,
                'nickname' => (string) $row['nickname'],
                'score'    => (int) $row['score'],
            ];
        }

        return ['scope' => $scope, 'entries' => $entries];
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @throws InvalidResponseException
     */
    private static function validatePayload(Poll $poll, array $payload): void
    {
        match ($poll->type) {
            PollType::FREITEXT => self::validateFreitextPayload($payload),
            PollType::MATRIX   => self::validateMatrixPayload($poll, $payload),
            PollType::MULTI    => self::validateMultiPayload($poll, $payload),
            default            => self::validateSelectedPayload($poll, $payload),
        };
    }

    /**
     * Multi-select: `selected` is a non-empty array of valid option IDs.
     * Duplicates are harmless (the client may send them twice) — aggregation
     * dedupes per response, here we only check validity.
     *
     * @param array<string,mixed> $payload
     */
    private static function validateMultiPayload(Poll $poll, array $payload): void
    {
        if (!array_key_exists('selected', $payload)) {
            throw new InvalidResponseException('Antwort-Payload muss `selected` enthalten.');
        }
        $selected = $payload['selected'];
        if (!is_array($selected) || $selected === []) {
            throw new InvalidResponseException('`selected` muss ein nicht-leeres Array von Optionen sein.');
        }
        if (count($selected) > count($poll->options)) {
            throw new InvalidResponseException('Mehr Auswahlen als Optionen.');
        }
        foreach ($selected as $id) {
            if (!is_string($id) || $id === '' || !$poll->hasOption($id)) {
                // Generic — do not leak valid IDs to the anonymous client.
                throw new InvalidResponseException('Ungültige Antwort-Option.');
            }
        }
    }

    /** @param array<string,mixed> $payload */
    private static function validateFreitextPayload(array $payload): void
    {
        if (!array_key_exists('text', $payload)) {
            throw new InvalidResponseException('Freitext-Payload muss `text` enthalten.');
        }
        $text = $payload['text'];
        if (!is_string($text) || trim($text) === '') {
            throw new InvalidResponseException('`text` darf nicht leer sein.');
        }
        if (mb_strlen($text) > 200) {
            throw new InvalidResponseException('`text` darf maximal 200 Zeichen lang sein.');
        }
    }

    /** @param array<string,mixed> $payload */
    private static function validateMatrixPayload(Poll $poll, array $payload): void
    {
        if (!array_key_exists('choices', $payload) || !is_array($payload['choices'])) {
            throw new InvalidResponseException('Matrix-Payload muss `choices` (Objekt) enthalten.');
        }
        // This method runs exclusively for matrix polls; their `options` carry
        // `rows` + `scale` (unlike the choice types with an options list).
        /** @var array{rows: list<array{id: string}>, scale: list<array{id: string}>} $options */
        $options   = $poll->options;
        $rows      = array_column($options['rows'], 'id');
        $scaleIds  = array_column($options['scale'], 'id');
        $choices   = $payload['choices'];

        // Fail closed: unknown keys are rejected, not silently stored.
        // Otherwise an attacker could inject arbitrary row/scale tokens that
        // later inflate the evaluation in `aggregateMatrixCountsForPoll`.
        foreach (array_keys($choices) as $key) {
            // PHP coerces numeric string keys ("0","1") to int, so compare
            // both sides as strings (row IDs are strings, e.g. "0".."n").
            // Generic message: do not mirror the client-sent token back to the
            // anonymous response (consistent with single-/multi-select validation).
            if (!in_array((string) $key, $rows, true)) {
                throw new InvalidResponseException('Ungültige Matrix-Antwort.');
            }
        }

        foreach ($rows as $rowId) {
            if (!array_key_exists($rowId, $choices)) {
                throw new InvalidResponseException('Unvollständige Matrix-Antwort.');
            }
            if (!in_array($choices[$rowId], $scaleIds, true)) {
                throw new InvalidResponseException('Ungültige Matrix-Antwort.');
            }
        }
    }

    /** @param array<string,mixed> $payload */
    private static function validateSelectedPayload(Poll $poll, array $payload): void
    {
        if (!array_key_exists('selected', $payload)) {
            throw new InvalidResponseException('Antwort-Payload muss `selected` enthalten.');
        }
        $selected = $payload['selected'];
        if (!is_string($selected) || $selected === '') {
            throw new InvalidResponseException('`selected` muss ein nicht-leerer String sein.');
        }
        if (!$poll->hasOption($selected)) {
            // Deliberately generic: do not mirror the list of valid option IDs
            // back to the anonymous client (information disclosure).
            throw new InvalidResponseException('Ungültige Antwort-Option.');
        }
    }

    /**
     * Creates a new poll (workplace wizard).
     *
     * Validates the wizard payload and creates the poll with a freshly
     * generated token + ID. Owner check is at the controller boundary
     * (`$GLOBALS['user']->id`).
     *
     * @param list<array{id?: string, label: string}> $options
     *
     * @throws InvalidResponseException on invalid payload (reused for
     *         wizard validation errors)
     */
    public function createPoll(
        string $userId,
        string $question,
        string $type,
        array $options,
        ?string $seminarId = null,
        ?int $durationSeconds = null,
        bool $quizMode = false,
        bool $resultsPublic = true,
    ): Poll {
        self::validateCreatePayload($question, $type, $options);

        // Normalize options: each gets an ID (if not provided), empty labels
        // are filtered out. Matrix and free text have different schemas.
        $normalized = match ($type) {
            PollType::MATRIX   => self::normalizeMatrixOptions($options),
            PollType::FREITEXT => [],
            default            => self::normalizeOptions($options),
        };

        $now  = time();
        // Positive seconds limit → absolute auto-stop timestamp.
        // 0/negative/null = no limit.
        $expiresAt = ($durationSeconds !== null && $durationSeconds > 0)
            ? $now + $durationSeconds
            : null;
        $poll = new Poll(
            id:           self::generateId(),
            token:        $this->generateUniqueToken(),
            seminarId:    ($seminarId === '' ? null : $seminarId),
            userId:       $userId,
            question:     trim($question),
            type:         $type,
            options:      $normalized,
            isActive:     true,
            mkdate:       $now,
            chdate:       $now,
            archivedAt:   null,
            parentPollId: null,
            expiresAt:    $expiresAt,
            // Quiz mode: opt-in, defaults to off; only single choice with at
            // least one option marked correct yields a playable quiz.
            quizMode:     $quizMode && $type === PollType::MC
                && array_filter($normalized, static fn (array $o) => !empty($o['correct'])) !== [],
            // Result visibility for students (opt-out, defaults to public).
            resultsPublic: $resultsPublic,
        );
        $this->repo->savePoll($poll);
        return $poll;
    }

    /**
     * @param list<array{label?: string}> $options
     *
     * @throws InvalidResponseException
     */
    private static function validateCreatePayload(string $question, string $type, array $options): void
    {
        if (trim($question) === '') {
            throw new InvalidResponseException('Frage-Text darf nicht leer sein.');
        }
        match (true) {
            in_array($type, PollType::OPTION_BASED, true) => self::validateListOptions($options),
            $type === PollType::FREITEXT                  => null, // no options needed
            $type === PollType::MATRIX                    => self::validateMatrixOptions($options),
            default => throw new InvalidResponseException(sprintf('Unbekannter Fragetyp "%s".', $type)),
        };
    }

    private static function validateListOptions(array $options): void
    {
        $nonEmpty = array_filter($options, static fn ($o) => trim((string) ($o['label'] ?? '')) !== '');
        if (count($nonEmpty) < 2) {
            throw new InvalidResponseException('Mindestens zwei Antwort-Optionen erforderlich.');
        }
        if (count($nonEmpty) > self::MAX_LIST_OPTIONS) {
            throw new InvalidResponseException(sprintf('Maximal %d Antwort-Optionen erlaubt.', self::MAX_LIST_OPTIONS));
        }
    }

    private static function validateMatrixOptions(array $options): void
    {
        if (!isset($options['rows']) || !is_array($options['rows'])
            || !isset($options['scale']) || !is_array($options['scale'])) {
            throw new InvalidResponseException('Matrix-Optionen müssen `rows` und `scale` enthalten.');
        }
        $rows  = array_filter($options['rows'],  static fn ($r) => trim((string) ($r['label'] ?? '')) !== '');
        $scale = array_filter($options['scale'], static fn ($s) => trim((string) ($s['label'] ?? '')) !== '');
        if (count($rows) < 2) {
            throw new InvalidResponseException('Matrix benötigt mindestens zwei Zeilen (rows).');
        }
        if (count($scale) < 2) {
            throw new InvalidResponseException('Matrix benötigt mindestens zwei Skalenwerte (scale).');
        }
        if (count($rows) > self::MAX_MATRIX_ROWS) {
            throw new InvalidResponseException(sprintf('Maximal %d Matrix-Zeilen erlaubt.', self::MAX_MATRIX_ROWS));
        }
        if (count($scale) > self::MAX_MATRIX_SCALE) {
            throw new InvalidResponseException(sprintf('Maximal %d Skalenwerte erlaubt.', self::MAX_MATRIX_SCALE));
        }
    }

    /**
     * Edits an existing poll.
     *
     * Question and course binding are always editable. Options may only be
     * changed while the poll has no responses — otherwise existing
     * `selected: optionId` responses would point to vanished IDs. With
     * `$options === null` options are left untouched (typical path for
     * edit-with-responses).
     *
     * Existing option IDs are preserved; new options get `opt-N` IDs
     * generated. This keeps aggregation queries (`LiveResultsStreamer`)
     * stable across edits.
     *
     * Owner check is at the controller boundary. The caller has verified
     * `$poll->userId === $GLOBALS['user']->id`.
     *
     * @param list<array{id?: string, label: string}>|null $options
     *
     * @throws PollNotFoundException
     * @throws InvalidResponseException
     */
    public function updatePoll(
        string $pollId,
        string $question,
        ?array $options,
        ?string $seminarId,
        ?bool $resultsPublic = null,
    ): Poll {
        $poll = $this->repo->findById($pollId);
        if ($poll === null) {
            throw new PollNotFoundException(sprintf('Poll-ID "%s" nicht gefunden.', $pollId));
        }

        $trimmedQuestion = trim($question);
        if ($trimmedQuestion === '') {
            throw new InvalidResponseException('Frage-Text darf nicht leer sein.');
        }

        $normalizedOptions = null;
        if ($options !== null) {
            // Sending options while responses exist yields a clear error
            // rather than a silent discard.
            if ($this->repo->countResponses($pollId) > 0) {
                throw new InvalidResponseException(
                    'Antwort-Optionen können nicht mehr geändert werden, '
                    . 'sobald die Umfrage Antworten erhalten hat.'
                );
            }
            $normalizedOptions = self::normalizeOptions($options);
            if (count($normalizedOptions) < 2) {
                throw new InvalidResponseException('Mindestens zwei Antwort-Optionen erforderlich.');
            }
        }

        $newSeminarId = ($seminarId === '' ? null : $seminarId);
        $this->repo->updatePollFields($pollId, $trimmedQuestion, $normalizedOptions, $newSeminarId);

        // Result visibility (opt-out) can be changed at any time — including
        // retroactively for already finished polls. `null` = unchanged.
        if ($resultsPublic !== null) {
            $this->repo->setResultsPublic($pollId, $resultsPublic);
        }

        // Reload so the caller gets the fresh state.
        return $this->repo->findById($pollId) ?? $poll;
    }

    /**
     * @param list<array{id?: string, label?: string, correct?: bool}> $options
     * @return list<array{id: string, label: string, correct?: bool}>
     */
    private static function normalizeOptions(array $options): array
    {
        $out = [];
        foreach ($options as $i => $opt) {
            $label = trim((string) ($opt['label'] ?? ''));
            if ($label === '') continue;
            $entry = [
                'id'    => (string) ($opt['id'] ?? sprintf('opt-%d', $i + 1)),
                'label' => $label,
            ];
            // Set the correct marker (quiz) only when true — keeps the JSON
            // schemas of non-quiz polls unchanged and slim.
            if (!empty($opt['correct'])) {
                $entry['correct'] = true;
            }
            $out[] = $entry;
        }
        return $out;
    }

    /** Normalizes matrix options: rows + scale get IDs if missing. */
    private static function normalizeMatrixOptions(array $options): array
    {
        $normalizeList = static function (array $list, string $prefix): array {
            $out = [];
            foreach ($list as $i => $item) {
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') continue;
                $out[] = ['id' => (string) ($item['id'] ?? sprintf('%s-%d', $prefix, $i + 1)), 'label' => $label];
            }
            return $out;
        };
        return [
            'rows'  => $normalizeList($options['rows']  ?? [], 'r'),
            'scale' => $normalizeList($options['scale'] ?? [], 's'),
        ];
    }

    /* ───────────────────────────── Lifecycle ─────────────────────────────
       The caller (controller) MUST have performed the owner check:
       `$poll->userId === $GLOBALS['user']->id`. The service does not re-check
       here because it is also used in the Trails auth layer and in CLI tools;
       authority stays at the controller boundary.
       ──────────────────────────────────────────────────────────────────── */

    public function finishPoll(string $pollId): void
    {
        $this->repo->setActive($pollId, false);
    }

    public function startPoll(string $pollId): void
    {
        $this->repo->setActive($pollId, true);
    }

    public function archivePoll(string $pollId): void
    {
        $this->repo->setArchivedAt($pollId, time());
    }

    public function unarchivePoll(string $pollId): void
    {
        $this->repo->setArchivedAt($pollId, null);
    }

    public function deletePoll(string $pollId): void
    {
        $this->repo->deletePollHard($pollId);
    }

    /**
     * Compare mode: creates a **new** poll with identical
     * question/options/seminar that references the original via
     * `parent_poll_id`. The original stays unchanged (with all its
     * responses). The new poll starts active immediately.
     *
     * @throws PollNotFoundException
     */
    public function restartAsCompare(string $originalId): Poll
    {
        return $this->cloneFrom($originalId, compare: true);
    }

    /**
     * Duplicate mode: creates a **standalone** new poll (no
     * `parent_poll_id`). The original stays unchanged. The new poll starts
     * active immediately.
     *
     * @throws PollNotFoundException
     */
    public function restartAsDuplicate(string $originalId): Poll
    {
        return $this->cloneFrom($originalId, compare: false);
    }

    /**
     * Shared logic for compare and duplicate restart. Creates a new poll
     * with its own token and its own response space.
     *
     * In compare mode (`$compare === true`) the new poll points **not** at
     * the immediate predecessor round but at the true chain root
     * (`resolveChainRoot`). Compare chains are flat by construction —
     * `findCompareChain` loads only one level (`id = ? OR parent_poll_id = ?`).
     * If an already-chained follow-up round (`parentPollId !== null`) were
     * restarted again in compare mode with its new `parentPollId` set to the
     * immediate original, a "grandchild" would appear that never shows up in
     * the root chain and would be unreachable in the workplace list (roots
     * only) yet keep collecting responses. Root resolution keeps all rounds flat.
     *
     * @throws PollNotFoundException
     */
    private function cloneFrom(string $originalId, bool $compare): Poll
    {
        $original = $this->repo->findById($originalId);
        if ($original === null) {
            throw new PollNotFoundException(sprintf('Poll-ID "%s" nicht gefunden.', $originalId));
        }

        $parentId = $compare ? $this->resolveChainRoot($original) : null;

        $now    = time();
        // Carry over the time limit. `expiresAt` is absolute (original
        // `mkdate` + duration); a 1:1 copy would already be expired on
        // restart. Instead apply the original duration (= expiresAt - mkdate)
        // fresh from NOW.
        $duration  = $original->expiresAt !== null
            ? max(0, $original->expiresAt - $original->mkdate)
            : 0;
        $expiresAt = $duration > 0 ? $now + $duration : null;

        $newPoll = new Poll(
            id:           self::generateId(),
            token:        $this->generateUniqueToken(),
            seminarId:    $original->seminarId,
            userId:       $original->userId,
            question:     $original->question,
            type:         $original->type,
            options:      $original->options,
            isActive:     true,
            mkdate:       $now,
            chdate:       $now,
            archivedAt:   null,
            parentPollId: $parentId,
            expiresAt:    $expiresAt,
            // Carry over the question's properties — otherwise a follow-up /
            // duplicate round would lose quiz mode and the results visibility.
            quizMode:      $original->quizMode,
            resultsPublic: $original->resultsPublic,
        );
        $this->repo->savePoll($newPoll);

        // Follow-up rounds of a compare chain inherit the original's
        // collection membership so all rounds of the same question land in
        // the same collection slot.
        if ($original->collectionId !== null && $original->collectionPosition !== null) {
            $collectionsRepo = new CollectionsRepository();
            $collectionsRepo->setPollMembership(
                $newPoll->id,
                $original->collectionId,
                $original->collectionPosition,
            );
        }

        return $newPoll;
    }

    /**
     * Walks the `parentPollId` chain up to the root and returns its ID. A
     * root (no `parentPollId`) returns its own ID. Robust against multi-level
     * chains: cycle protection via `$seen`, and an orphaned `parentPollId`
     * (parent deleted) stops and treats the current poll as the root.
     */
    private function resolveChainRoot(Poll $poll): string
    {
        $current = $poll;
        $seen    = [];
        while ($current->parentPollId !== null && !isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $parent = $this->repo->findById($current->parentPollId);
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }
        return $current->id;
    }

    /* ───────────────────── Compare-Chain (Peer Instruction) ───────────────── */

    /**
     * Loads the full compare chain of a root poll and checks the owner
     * server-side: only the creator may view a chain of their own polls.
     *
     * Information-leakage protection: same exception as "not found" so that
     * status-code discrimination cannot reveal foreign poll IDs.
     *
     * One DB query loads the chain (`findCompareChain`), a second the counts
     * (`aggregateCountsForPolls`) — both batched, no N+1.
     *
     * @throws PollNotFoundException
     */
    public function loadCompareChain(string $rootId, string $callerUserId, bool $allowAnyOwner = false): CompareChain
    {
        $polls = $this->repo->findCompareChain($rootId);
        if (count($polls) === 0) {
            throw new PollNotFoundException(sprintf('Vergleichs-Kette für "%s" nicht gefunden.', $rootId));
        }
        // The root is by construction the first element of the list sorted
        // by `mkdate ASC` — children are created via `restartAsCompare`,
        // hence always AFTER the root.
        // `$allowAnyOwner` = the controller has already verified tutor access
        // in the root poll's seminar (co-teaching, consistent with
        // poll/stream/export). Then owner identity is no longer required.
        $root = $polls[0];
        if (!$allowAnyOwner && $root->userId !== $callerUserId) {
            throw new PollNotFoundException(sprintf('Vergleichs-Kette für "%s" nicht gefunden.', $rootId));
        }

        $ids    = array_map(static fn (Poll $p): string => $p->id, $polls);
        $counts = $this->repo->aggregateCountsForPolls($ids);

        return new CompareChain(polls: $polls, counts: $counts);
    }

    /**
     * Data basis for blind mode: returns `true` when the given poll has at
     * least one active follow-up poll. While that holds, the polls app of the
     * root poll must not deliver answer counts, so students are not swayed in
     * round 2 by round 1's majority opinion.
     */
    public function isBlindModeActive(string $pollId): bool
    {
        return $this->repo->hasActiveDescendant($pollId);
    }

    private static function generateToken(): string
    {
        // 8 base32-like chars, memorable for QR/manual-entry paths
        $alphabet = 'ABCDEFGHJKMNPQRSTVWXYZ23456789';
        $rng      = new Randomizer();
        $out      = '';
        for ($i = 0; $i < 8; $i++) {
            $out .= $alphabet[$rng->getInt(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    /**
     * Token with collision pre-check. 8 chars from a 29-char alphabet ≈ 39
     * bits; as the dataset grows the birthday paradox becomes relevant, and
     * without a pre-check the UNIQUE index would surface a collision as a DB
     * exception (500) on insert. Here we check against `findByToken` first and
     * re-roll; the UNIQUE index remains the final backstop for the
     * (astronomically rare) concurrent double hit.
     */
    private function generateUniqueToken(int $maxAttempts = 5): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $token = self::generateToken();
            if ($this->repo->findByToken($token) === null) {
                return $token;
            }
        }
        return self::generateToken();
    }

    private static function generateId(): string
    {
        // 32 hex chars like Stud.IP IDs — low collision risk + filterable
        return bin2hex((new Randomizer())->getBytes(16));
    }
}
