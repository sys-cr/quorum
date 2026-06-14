<?php

declare(strict_types=1);

use Quorum\Polls\Poll;
use Quorum\Polls\PollType;
use Quorum\Polls\PollsService;
use Quorum\Polls\PollsRepository;
use Quorum\Polls\LiveResultsStreamer;
use Quorum\Export\ResultsCsvExporter;
use Quorum\Polls\Exceptions\InvalidResponseException;
use Quorum\Polls\Exceptions\PollInactiveException;
use Quorum\Polls\Exceptions\PollNotFoundException;

/**
 * Polls API for the anonymous student polls-app — routes:
 *   GET  /plugins.php/quorumstudipplugin/api/polls/{token}        → ApiController::polls_action
 *   POST /plugins.php/quorumstudipplugin/api/responses/{pollId}   → ApiController::responses_action
 *
 * Anonymous access via `$allow_nobody = true` + the plugin nobody role (set in
 * `QuorumStudipPlugin::onEnable()`).
 *
 * Responses are JSON; errors follow a slim
 * `{ "error": "<code>", "message": "<text>" }` format.
 */
class ApiController extends PluginController
{
    use \Quorum\Controllers\SeminarAccessTrait;

    protected $allow_nobody = true;

    private PollsService $service;
    private LiveResultsStreamer $streamer;

    public function before_filter(&$action, &$args): void
    {
        parent::before_filter($action, $args);
        $repo            = new PollsRepository();
        // Free-text blocklist from the admin-configurable Stud.IP config
        // (empty = moderation off).
        $blocklist       = (string) (\Config::get()->QUORUM_FREITEXT_BLOCKLIST ?? '');
        $this->service   = new PollsService($repo, \Quorum\Moderation\TextModerator::fromCsv($blocklist));
        $this->streamer  = new LiveResultsStreamer($repo);
    }

    /**
     * GET /api/polls/{token} — returns the active poll for the token.
     */
    public function polls_action(string $token = ''): void
    {
        if ($token === '') {
            $this->renderJsonError(400, 'missing_token', 'URL-Token fehlt.');
            return;
        }

        try {
            $poll = $this->service->findActivePollByToken($token);
            // Blind mode flag: while a follow-up poll (round 2) is active, the
            // polls-app suppresses any display of round-1 results.
            $blind = $this->service->isBlindModeActive($poll->id);
            $now   = time();
            // Countdown data. The server clock is authoritative — the client
            // derives remaining time relative to `server_now`, not its own
            // (possibly skewed) clock.
            $data  = $poll->withBlindMode($blind)->toApiArray();
            // Participants get the options WITHOUT `correct` flags — otherwise
            // the right answer would be visible in the network tab (decisive
            // in quiz mode).
            $data['options']           = $poll->optionsForParticipants();
            $data['remaining_seconds'] = $poll->remainingSeconds($now);
            $data['server_now']        = $now;
            $this->renderJson(200, $data);
        } catch (PollNotFoundException) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht oder ist abgelaufen.');
        } catch (PollInactiveException) {
            // Make the waiting state distinguishable from the ended state so
            // the polls-app does not falsely show "finished" before the start.
            // `statusForToken` cannot throw NotFound here (the poll was
            // already found above).
            $status = $this->service->statusForToken($token);
            $this->renderJson(410, [
                'error'        => 'poll_inactive',
                'message'      => 'Diese Abstimmung ist gerade nicht aktiv.',
                'status'       => $status['status'],
                'active_token' => $status['active_token'],
            ]);
        }
    }

    /**
     * GET /api/poll_status/{token} — lightweight status view for the
     * participant live sync. Anonymous like `polls_action`; the polls-app
     * polls it at an interval and reacts to voting start/stop without a
     * reload. Deliberately polling instead of SSE: hundreds of anonymous
     * participant streams would each pin a PHP-FPM worker.
     *
     * Response: { status: active|paused|ended, active_token: string|null,
     *             server_now: int }
     */
    public function poll_status_action(string $token = ''): void
    {
        if ($token === '') {
            $this->renderJsonError(400, 'missing_token', 'URL-Token fehlt.');
            return;
        }

        try {
            $status = $this->service->statusForToken($token);
            $status['server_now'] = time();
            $this->renderJson(200, $status);
        } catch (PollNotFoundException) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht oder ist abgelaufen.');
        }
    }

    /**
     * GET /api/leaderboard/{token} — pseudonymous quiz leaderboard.
     *
     * Anonymous like `polls_action` (participants see it after answering, the
     * presenter on the projector). If the question belongs to a collection, the
     * sum is taken over all member questions. Only freely chosen nicknames +
     * points leave the server — no real names, no IDs.
     */
    public function leaderboard_action(string $token = ''): void
    {
        if ($token === '') {
            $this->renderJsonError(400, 'missing_token', 'URL-Token fehlt.');
            return;
        }
        try {
            $this->renderJson(200, $this->service->leaderboardForToken($token));
        } catch (PollNotFoundException) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht oder ist abgelaufen.');
        }
    }

    /**
     * GET /api/poll_solution/{token} — quiz solution for the learning effect.
     *
     * Anonymous. Returns the correct options ONLY once the quiz question has
     * ended (no longer active) — beforehand 403, so the solution cannot be
     * grabbed during the vote (leaderboard protection). The participant page
     * compares it client-side with the locally stored answer.
     */
    public function poll_solution_action(string $token = ''): void
    {
        if ($token === '') {
            $this->renderJsonError(400, 'missing_token', 'URL-Token fehlt.');
            return;
        }
        try {
            $solution = $this->service->quizSolutionForToken($token);
            if ($solution === null) {
                $this->renderJsonError(403, 'solution_unavailable', 'Die richtige Antwort wird erst nach Ende der Frage angezeigt.');
                return;
            }
            $this->renderJson(200, $solution);
        } catch (PollNotFoundException) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht oder ist abgelaufen.');
        }
    }

    /**
     * POST /api/responses/{pollId} — stores a response.
     */
    public function responses_action(string $pollId = ''): void
    {
        if (Request::method() !== 'POST') {
            $this->renderJsonError(405, 'method_not_allowed', 'POST erforderlich.');
            return;
        }
        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        // CSRF mitigation for the anonymous endpoint: the polls-app is a
        // standalone mobile page (no Stud.IP PageLayout) without a reliable
        // session token, so `verifyUnsafeRequest()` would reject every
        // legitimate vote. Instead a same-origin check: cross-site
        // `fetch`/`form` submits carry a foreign `Origin` header and are
        // rejected. If both Origin AND Referer are absent, the request is
        // rejected fail-closed (real browsers always send an Origin header
        // on a fetch POST).
        if (!self::isSameOrigin()) {
            $this->renderJsonError(403, 'forbidden_origin', 'Anfrage von fremder Herkunft abgelehnt.');
            return;
        }

        $payload = self::decodeJsonBody();
        if ($payload === null) {
            $this->renderJsonError(400, 'invalid_json', 'Request-Body ist kein gültiges JSON oder zu groß.');
            return;
        }

        try {
            $response = $this->service->recordResponse($pollId, $payload);
            $this->renderJson(201, ['accepted' => true, 'response_id' => $response->id]);
        } catch (PollNotFoundException) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
        } catch (PollInactiveException) {
            $this->renderJsonError(410, 'poll_inactive', 'Diese Abstimmung ist beendet.');
        } catch (InvalidResponseException $e) {
            $this->renderJsonError(422, 'invalid_response', $e->getMessage());
        }
    }

    /**
     * SSE live stream of aggregated voting counts.
     *
     * Routes (Trails routes flat `/{controller}/{action}/{args}` — so
     * `stream_action` lives at `/api/stream/{id}`, NOT nested under
     * `/api/polls/{id}/stream`, which would map to `polls_action(id,"stream")`
     * → 404):
     *   GET /api/stream/{pollId}                            (SSE, default)
     *   GET /api/stream/{pollId}  Accept: application/json  (polling fallback,
     *                                  single JSON snapshot)
     *
     * Operational note: behind Stud.IP/nginx/PHP-FPM, long-lived responses can
     * buffer despite `X-Accel-Buffering: no` — clients (PresenterStage,
     * useLiveResults) have a first-data watchdog and fall back to the JSON
     * polling branch after 4 s of a silent connection.
     *
     * Auth: lecturer (`tutor` level) for the associated course. The poll is
     * loaded from the repo; its `seminar_id` determines the auth context.
     * Anonymous students or foreign lecturers get 403.
     *
     * Stream lifecycle:
     *   - `Content-Type: text/event-stream`, `X-Accel-Buffering: no`
     *   - first iteration immediately, then every 2 s; heartbeat every 30 s
     *   - aborted via `connection_aborted()` (client closes tab / network change)
     *   - hard limit after 1 h (worker protection; client reconnects automatically)
     */
    public function stream_action(string $pollId = ''): void
    {
        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $poll = $this->service->findPollById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }

        // Auth strategy per poll type:
        //   - course poll (seminarId != null): tutor level in that seminar
        //   - global poll  (seminarId == null): only the creator
        $userId = $GLOBALS['user']->id ?? '';
        $isOwner    = ($userId !== '' && $userId === $poll->userId);
        $hasSemPerm = $poll->seminarId !== null
            && $GLOBALS['perm']->have_studip_perm('tutor', $poll->seminarId);
        if (!$isOwner && !$hasSemPerm) {
            $this->renderJsonError(403, 'forbidden', 'Sie haben keinen Zugriff auf diese Abstimmung.');
            return;
        }

        // Polling fallback: single JSON snapshot instead of a long-lived stream.
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            $this->renderJson(200, $this->streamer->aggregateCounts($pollId));
            return;
        }

        // SSE-Stream
        $this->set_status(200);
        $this->set_content_type('text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');     // nginx: no output buffering
        header('Connection: keep-alive');

        @set_time_limit(0);
        @ignore_user_abort(false);

        $deadline       = time() + 3600;
        $lastHeartbeat  = time();
        $lastCounts     = null;

        while (!connection_aborted() && time() < $deadline) {
            $counts = $this->streamer->aggregateCounts($pollId);
            // Normalize key order: `aggregateCounts` builds the map via
            // `GROUP BY` without `ORDER BY`, so `!==` would otherwise re-send
            // identical counts in a different order.
            ksort($counts);
            if ($counts !== $lastCounts) {
                echo $this->streamer->formatEvent($counts);
                $lastCounts = $counts;
            }
            if (time() - $lastHeartbeat >= 30) {
                echo $this->streamer->formatHeartbeat();
                $lastHeartbeat = time();
            }
            @ob_flush();
            @flush();
            sleep(2);
        }

        $this->render_nothing();
    }

    /**
     * GET /api/my_polls — workplace widget (Trails routes flat).
     *
     * Returns all polls created by the logged-in user, incl. seminar name +
     * response count. Auth: Stud.IP session required (no anonymous access —
     * `nobody` is rejected). No IDOR: the repository WHERE filters `userId`
     * from `$GLOBALS['user']->id`, so an attacker cannot request foreign data
     * via query parameter.
     *
     * Response: `{ "polls": [...] }` with the fields from `PollSummary::toApiArray()`.
     */
    public function my_polls_action(): void
    {
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        // ?view=active|archive|all — default `active`. The filter is applied
        // server-side; the client cannot use "all" as a backdoor since the
        // owner check runs before the filter.
        $view = (string) (\Request::option('view') ?: 'active');
        if (!in_array($view, ['active', 'archive', 'all'], true)) {
            $view = 'active';
        }

        $repo = new PollsRepository();
        $summaries = $repo->findSummariesByUser($userId, $view);

        // Include the anonymous join URL (QR target) per poll so the workplace
        // frontend can render a QR code / share link.
        $plugin = $this->plugin;
        $this->renderJson(200, [
            'view'  => $view,
            'polls' => array_map(
                static fn ($s) => $s->toApiArray() + ['join_url' => $plugin->pollJoinUrl($s->token)],
                $summaries,
            ),
        ]);
    }

    /**
     * GET /api/course_polls?cid={cid} — course-app course tab.
     *
     * Returns ALL root polls of course `$cid` (active + archived) as a flat
     * list. Unlike `my_polls` (user-bound) this is course-bound so co-lecturers
     * in the course tab see the same state.
     *
     * Auth: tutor level in the course. Anonymous/foreign users get 403. No
     * IDOR — polls are filtered server-side by `seminar_id = cid`.
     *
     * Response: `{ "cid": "...", "polls": [ … + join_url ] }`
     */
    public function course_polls_action(): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        if ($this->requireOwnerUserId() === null) return;

        $cid = (string) \Request::option('cid', '');
        if ($cid === '') {
            $this->renderJsonError(400, 'missing_cid', 'Veranstaltungs-ID (cid) fehlt.');
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_studip_perm('tutor', $cid)) {
            $this->renderJsonError(403, 'forbidden', 'Keine Berechtigung für diese Veranstaltung.');
            return;
        }

        $repo      = new PollsRepository();
        $summaries = $repo->findSummariesBySeminar($cid, 'all');
        $plugin    = $this->plugin;

        $this->renderJson(200, [
            'cid'   => $cid,
            'polls' => array_map(
                static fn ($s) => $s->toApiArray() + ['join_url' => $plugin->pollJoinUrl($s->token)],
                $summaries,
            ),
        ]);
    }

    /**
     * GET /api/course_collections?cid=…[&view=active|archive|all] —
     * collections of ONE course for the teacher view of the course tab.
     * Auth: tutor level in the course (co-teaching). Listed via the course
     * assignment `c.seminar_id`, not via the owner.
     */
    public function course_collections_action(): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        if ($this->requireOwnerUserId() === null) return;

        $cid = (string) \Request::option('cid', '');
        if ($cid === '') {
            $this->renderJsonError(400, 'missing_cid', 'Veranstaltungs-ID (cid) fehlt.');
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_studip_perm('tutor', $cid)) {
            $this->renderJsonError(403, 'forbidden', 'Keine Berechtigung für diese Veranstaltung.');
            return;
        }

        $view    = (string) \Request::option('view', 'active');
        $view    = in_array($view, ['active', 'archive', 'all'], true) ? $view : 'active';
        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new PollsRepository(),
        );
        $this->renderJson(200, [
            'cid'         => $cid,
            'collections' => array_map(
                static fn ($s) => $s->toApiArray(),
                $service->findSummariesBySeminar($cid, $view),
            ),
        ]);
    }

    /**
     * GET /api/course_student_polls?cid=… — student view of the course tab.
     *
     * Auth: logged-in course member (at least `user` level). Returns the
     * currently running polls with an anonymous join link plus the finished
     * polls whose results the teacher did NOT hide (`results_public`, opt-out).
     * No management data, no `correct` flags, no identity link — the client
     * fetches the result counts per poll via `course_student_results`.
     */
    public function course_student_polls_action(): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        if ($this->requireOwnerUserId() === null) return;

        $cid = (string) \Request::option('cid', '');
        if ($cid === '') {
            $this->renderJsonError(400, 'missing_cid', 'Veranstaltungs-ID (cid) fehlt.');
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_studip_perm('user', $cid)) {
            $this->renderJsonError(403, 'forbidden', 'Keine Berechtigung für diese Veranstaltung.');
            return;
        }

        $repo   = new PollsRepository();
        $plugin = $this->plugin;
        $active = [];
        $finished = [];
        foreach ($repo->findStudentCoursePolls($cid) as $row) {
            if ($row['is_active']) {
                $active[] = [
                    'id'       => $row['id'],
                    'token'    => $row['token'],
                    'question' => $row['question'],
                    'type'     => $row['type'],
                    'join_url' => $plugin->pollJoinUrl($row['token']),
                ];
            } elseif ($row['results_public']) {
                $finished[] = [
                    'id'       => $row['id'],
                    'question' => $row['question'],
                    'type'     => $row['type'],
                    'mkdate'   => $row['mkdate'],
                ];
            }
        }

        $this->renderJson(200, ['cid' => $cid, 'active' => $active, 'finished' => $finished]);
    }

    /**
     * GET /api/course_student_collections?cid=… — student view of a course's
     * collections. Auth: course member (`user` level). Per collection the
     * currently running member questions (anonymous join link) + the finished,
     * released questions (result review). Anonymous: no per-person status;
     * `results_public` respected per member.
     */
    public function course_student_collections_action(): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        if ($this->requireOwnerUserId() === null) return;

        $cid = (string) \Request::option('cid', '');
        if ($cid === '') {
            $this->renderJsonError(400, 'missing_cid', 'Veranstaltungs-ID (cid) fehlt.');
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_studip_perm('user', $cid)) {
            $this->renderJsonError(403, 'forbidden', 'Keine Berechtigung für diese Veranstaltung.');
            return;
        }

        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new PollsRepository(),
        );
        $plugin = $this->plugin;
        $out    = [];
        foreach ($service->findStudentCourseCollections($cid) as $col) {
            $out[] = [
                'id'          => $col['id'],
                'name'        => $col['name'],
                'description' => $col['description'],
                'active'      => array_map(static fn (array $p) => [
                    'id'       => $p['id'],
                    'question' => $p['question'],
                    'type'     => $p['type'],
                    'join_url' => $plugin->pollJoinUrl($p['token']),
                ], $col['active']),
                'finished'    => $col['finished'],
            ];
        }

        $this->renderJson(200, ['cid' => $cid, 'collections' => $out]);
    }

    /**
     * GET /api/course_student_results/{pollId} — aggregated results of ONE
     * finished poll for the student view.
     *
     * Access only when the caller is a member of the poll's course, the
     * results are released (`results_public`) and the poll is no longer running
     * (not effectively active) and not archived. Otherwise 404 (no
     * existence/status oracle). Only aggregates, without `correct` flags; free
     * text shows only the non-moderated (deleted) responses.
     */
    public function course_student_results_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        if ($this->requireOwnerUserId() === null) return;

        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        $perm = $GLOBALS['perm'] ?? null;
        $cid  = $poll?->seminarId;
        $isMember = $cid !== null && $perm && $perm->have_studip_perm('user', $cid);

        if ($poll === null
            || !$isMember
            || !$poll->resultsPublic
            || $poll->isArchived()
            || $poll->isEffectivelyActive(time())
        ) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }

        $payload = [
            'type'     => $poll->type,
            'question' => $poll->question,
            'options'  => $poll->optionsForParticipants(),
        ];
        // Learning effect: for a quiz, mark the correct answer — the question
        // is guaranteed to have ended here (checked above), so this is safe.
        if ($poll->quizMode) {
            $payload['correct'] = $poll->correctOptionIds();
        }
        if ($poll->type === 'matrix') {
            $payload['counts'] = $repo->aggregateMatrixCountsForPoll($pollId);
        } elseif ($poll->type === 'freitext') {
            $payload['responses'] = array_values($repo->findFreitextResponses($pollId));
        } else {
            $payload['counts'] = $repo->aggregateCountsForPoll($pollId);
        }

        $this->renderJson(200, $payload);
    }

    /**
     * GET /api/poll/{id} — course-app voting detail.
     *
     * Lecturer detail view of a poll: question, options, lifecycle status,
     * aggregated choice counts (mc/scales/emoji) and `join_url` (QR target).
     *
     * Auth: owner OR tutor level in the poll's course. Foreign access → 404
     * (information-leakage protection, same as "not found"). Matrix/free-text
     * detail data come from the dedicated endpoints (`matrix_counts` /
     * `freitext_responses`).
     */
    public function poll_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        $perm    = $GLOBALS['perm'] ?? null;
        if (!self::callerMayAccessPoll($poll, $userId, $perm)) {
            // 404 instead of 403 — don't reveal foreign poll IDs as existing.
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }

        $this->renderJson(200, $poll->toApiArray() + [
            'is_active'      => $poll->isActive,
            'archived_at'    => $poll->archivedAt,
            'parent_poll_id' => $poll->parentPollId,
            'mkdate'         => $poll->mkdate,
            'response_count' => $repo->countResponses($poll->id),
            // children_count: the detail page only shows "show comparison" when
            // the poll has follow-up runs (parity with the card menu).
            'children_count' => $repo->countChildren($poll->id),
            'join_url'       => $this->plugin->pollJoinUrl($poll->token),
            // Short link (created lazily/idempotently) — the QR dialog prefers
            // it over the long join_url.
            'short_url'      => $this->plugin->registerPollShortLink($poll->token, $poll->userId, $poll->question),
            // Choice counts (mc/scales/emoji). Matrix/free-text → own endpoints.
            'counts'         => $repo->aggregateCountsForPoll($poll->id),
        ]);
    }

    /**
     * GET /api/export/{id}?format=csv — results export.
     *
     * Returns a poll's aggregated results as a CSV download (option, votes,
     * percent). Anonymous aggregates only, no PII; the filename uses the public
     * token. Only CSV is supported (other formats → 501).
     *
     * Auth: owner OR tutor level in the course; foreign → 404.
     */
    public function export_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;
        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        $perm    = $GLOBALS['perm'] ?? null;
        if (!self::callerMayAccessPoll($poll, $userId, $perm)) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }

        $format = strtolower((string) \Request::option('format', 'csv'));
        if (!in_array($format, ['csv', 'pdf'], true)) {
            $this->renderJsonError(501, 'format_unavailable',
                'Unbekanntes Export-Format — verfügbar sind CSV und PDF.');
            return;
        }
        // Collect data per question type — export applies to ALL types
        // (per user feedback): choice as option/votes/percent table,
        // free text as a response list, matrix as a rows×scale table.
        $pdf = new \Quorum\Export\ResultsPdfExporter();
        $csv = new ResultsCsvExporter();
        $stand = static fn (int $total): string =>
            sprintf(_quorum('%d Stimmen · Stand: %s'), $total, date('d.m.Y H:i'));

        if ($poll->type === PollType::FREITEXT) {
            $responses = $repo->findFreitextResponses($poll->id);
            $content   = $format === 'pdf'
                ? $pdf->buildFreitextHtml($poll->question, $responses, _quorum('Antwort'), $stand(count($responses)))
                : $csv->exportFreitext($responses, _quorum('Antwort'), withBom: true);
        } elseif ($poll->type === PollType::MATRIX) {
            $rows    = $poll->options['rows']  ?? [];
            $scale   = $poll->options['scale'] ?? [];
            $mcounts = $repo->aggregateMatrixCountsForPoll($poll->id);
            $total   = array_sum(array_map('array_sum', $mcounts));
            $content = $format === 'pdf'
                ? $pdf->buildMatrixHtml($poll->question, $rows, $scale, $mcounts, $stand($total))
                : $csv->exportMatrix($rows, $scale, $mcounts, withBom: true);
        } else {
            $counts  = $repo->aggregateCountsForPoll($poll->id);
            $headers = [_quorum('Option'), _quorum('Stimmen'), _quorum('Prozent')];
            $content = $format === 'pdf'
                ? $pdf->buildHtml($poll->question, $poll->options, $counts, $headers, $stand(array_sum($counts)))
                : $csv->exportPoll($poll->options, $counts, $headers, withBom: true);
        }

        // PDF: Stud.IP's `\ExportPDF` (TCPDF) with the standard header/footer —
        // the HTML comes from the unit-tested exporters, this is rendering
        // only. `dispatch()` streams the PDF inline to the browser.
        if ($format === 'pdf') {
            $doc = new \ExportPDF();
            $doc->setHeaderTitle('Quorum');
            $doc->addPage();
            $doc->writeHTML($content);
            $doc->dispatch('quorum-poll-' . $poll->token);
            $this->render_nothing();
            return;
        }

        $this->set_status(200);
        $this->set_content_type('text/csv; charset=utf-8');
        // Token is the public slug (no PII) and filename-safe.
        header('Content-Disposition: attachment; filename="quorum-poll-' . $poll->token . '.csv"');
        $this->render_text($content);
    }

    /**
     * GET /api/download/{id} — survey DEFINITION as portable JSON.
     *
     * Unlike `export_action` (aggregated RESULTS as CSV) this returns only the
     * reusable definition (question, type, options) for the "download → import"
     * round trip (see SurveyDefinition). No responses, no PII.
     *
     * Auth: owner OR tutor level in the course; foreign → 404.
     */
    public function download_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;
        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!self::callerMayAccessPoll($poll, $userId, $perm)) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        // Only export round-trip-capable types. Matrix has a row/scale schema
        // that the flat definition cannot carry (and the import would reject it).
        if (!in_array($poll->type, PollType::SIMPLE_FORM, true)) {
            $this->renderJsonError(422, 'type_not_exportable',
                'Dieser Fragetyp (Matrix) kann nicht als Definition exportiert werden.');
            return;
        }

        $json = \Quorum\Polls\SurveyDefinition::toJson($poll, time());

        $this->set_status(200);
        $this->set_content_type('application/json; charset=utf-8');
        // Token is the public slug (no PII) and filename-safe.
        header('Content-Disposition: attachment; filename="quorum-umfrage-' . $poll->token . '.json"');
        $this->render_text($json);
    }

    /**
     * GET /api/download_collection/{id} — COLLECTION definition as JSON.
     *
     * Name + description + ordered member surveys (definitions, no responses)
     * for the "download collection → import" round trip.
     *
     * Auth: collections are owner-bound (no co-teaching) → owner only.
     */
    public function download_collection_action(string $collectionId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;
        if ($collectionId === '') {
            $this->renderJsonError(400, 'missing_collection_id', 'Sammlungs-ID fehlt.');
            return;
        }

        $service    = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new PollsRepository(),
        );
        $collection = $service->findCollectionById($collectionId);
        if ($collection === null || $collection->userId !== $userId) {
            // Information-leakage protection: 404 for foreign/missing IDs.
            $this->renderJsonError(404, 'collection_not_found', 'Diese Sammlung existiert nicht.');
            return;
        }

        $polls = $service->findPollsInCollection($collection->id);
        // A matrix member survey cannot be serialized round-trip-capable (and
        // the import would reject the collection). Reject clearly instead of
        // producing a broken file.
        foreach ($polls as $p) {
            if (!in_array((string) $p['type'], PollType::SIMPLE_FORM, true)) {
                $this->renderJsonError(422, 'type_not_exportable',
                    'Diese Sammlung enthält eine Matrix-Umfrage und kann nicht als Definition exportiert werden.');
                return;
            }
        }
        $json  = \Quorum\Polls\CollectionDefinition::toJson($collection, $polls, time());

        $this->set_status(200);
        $this->set_content_type('application/json; charset=utf-8');
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $collection->name) ?: 'sammlung';
        header('Content-Disposition: attachment; filename="quorum-sammlung-' . trim((string) $slug, '-') . '.json"');
        $this->render_text($json);
    }

    /* ─────────────── Collection list + lifecycle ─────────────── */

    /**
     * GET /api/my_collections?view=active|archive — collection list of the
     * logged-in owner for the Vue collection cards (analogous to `my_polls`).
     *
     * Auth: session required; IDOR ruled out because the repository WHERE
     * filters by the `userId` from the session.
     *
     * Response: `{ "view": "...", "collections": [CollectionSummary…] }`
     * including `active_count` (number of currently running member surveys)
     * for the lifecycle actions in the card menu.
     */
    public function my_collections_action(): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        $view = (string) (\Request::option('view') ?: 'active');
        if (!in_array($view, ['active', 'archive', 'all'], true)) {
            $view = 'active';
        }

        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new PollsRepository(),
        );
        $this->renderJson(200, [
            'view'        => $view,
            'collections' => array_map(
                static fn ($s) => $s->toApiArray(),
                $service->findSummariesByUser($userId, $view),
            ),
        ]);
    }

    /**
     * POST /api/collection_start/{id} — start voting for the collection.
     * Body: `{ "mode": "all" | "step" }` (default `all`).
     *   - all:  all member questions active (students click through on their
     *           own, the owner finishes later).
     *   - step: only question 1 active — advancing is handled by the
     *           presenter ("start next question").
     */
    public function collection_start_action(string $collectionId = ''): void
    {
        $this->withOwnedCollection($collectionId, 'POST', function (\Quorum\Polls\Collection $c, \Quorum\Polls\CollectionsService $service): void {
            $mode = (string) ((self::decodeJsonBody()['mode'] ?? null) ?: 'all');
            try {
                $service->startCollection($c->id, $mode);
                $this->renderJson(200, ['ok' => true, 'mode' => $mode]);
            } catch (InvalidResponseException $e) {
                $this->renderJsonError(422, 'collection_not_startable', $e->getMessage());
            }
        });
    }

    /** POST /api/collection_finish/{id} — stop voting for ALL member questions. */
    public function collection_finish_action(string $collectionId = ''): void
    {
        $this->withOwnedCollection($collectionId, 'POST', function (\Quorum\Polls\Collection $c, \Quorum\Polls\CollectionsService $service): void {
            $service->finishCollection($c->id);
            $this->renderJson(200, ['ok' => true]);
        });
    }

    /** POST /api/collection_archive/{id} — `archived_at := now()`. */
    public function collection_archive_action(string $collectionId = ''): void
    {
        $this->withOwnedCollection($collectionId, 'POST', function (\Quorum\Polls\Collection $c, \Quorum\Polls\CollectionsService $service): void {
            $service->archiveCollection($c->id);
            $this->renderJson(200, ['ok' => true, 'archived_at' => time()]);
        });
    }

    /** POST /api/collection_unarchive/{id} — `archived_at := NULL`. */
    public function collection_unarchive_action(string $collectionId = ''): void
    {
        $this->withOwnedCollection($collectionId, 'POST', function (\Quorum\Polls\Collection $c, \Quorum\Polls\CollectionsService $service): void {
            $service->unarchiveCollection($c->id);
            $this->renderJson(200, ['ok' => true, 'archived_at' => null]);
        });
    }

    /**
     * DELETE /api/collection_delete/{id} — hard delete of the collection.
     * Member polls are preserved (they become free-standing).
     */
    public function collection_delete_action(string $collectionId = ''): void
    {
        $this->withOwnedCollection($collectionId, 'DELETE', function (\Quorum\Polls\Collection $c, \Quorum\Polls\CollectionsService $service): void {
            $service->deleteCollection($c->id);
            $this->renderJson(200, ['ok' => true, 'deleted' => $c->id]);
        });
    }

    /**
     * POST /api/create — create a new poll (flat, workplace wizard). Body:
     *   `{ question: string, type: 'mc'|'scales', options: [{label}…],
     *      seminar_id: string|null }`
     *
     * Auth: Stud.IP session + CSRF; server-side owner assignment via
     * `$GLOBALS['user']->id` (client cannot create on behalf of others).
     */
    public function create_action(): void
    {
        if (\Request::method() !== 'POST') {
            $this->renderJsonError(405, 'method_not_allowed', 'POST erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        try {
            \CSRFProtection::verifyUnsafeRequest();
        } catch (\Throwable) {
            $this->renderJsonError(403, 'csrf_failed', 'CSRF-Token ungültig oder fehlt.');
            return;
        }

        $body       = self::decodeJsonBody() ?? [];
        $question   = (string) ($body['question']   ?? '');
        $type       = (string) ($body['type']       ?? 'mc');
        $options    =          ($body['options']    ?? []);
        $seminarId  =          ($body['seminar_id'] ?? null);
        $seminarId  = ($seminarId === '' || $seminarId === null) ? null : (string) $seminarId;
        // Optional time limit in seconds (0/absent = no limit).
        $duration   = isset($body['duration_seconds']) ? (int) $body['duration_seconds'] : null;

        if (!is_array($options)) {
            $this->renderJsonError(400, 'invalid_options', 'options muss ein Array sein.');
            return;
        }

        // Binding to a seminar requires tutor rights there.
        if (!$this->mayWriteSeminar($seminarId)) {
            $this->renderJsonError(403, 'forbidden_seminar', 'Keine Berechtigung für diese Veranstaltung.');
            return;
        }

        try {
            // matrix: options is an associative dict {rows:[…], scale:[…]} — must not flatten
            $normalizedOptions = array_is_list($options) ? array_values($options) : $options;
            $poll = $this->service->createPoll(
                userId:          $userId,
                question:        $question,
                type:            $type,
                options:         $normalizedOptions,
                seminarId:       $seminarId,
                durationSeconds: $duration,
            );
            // Register short link (best-effort, appears in "My short links").
            // Never aborts creation.
            $shortUrl = $this->plugin->registerPollShortLink($poll->token, $userId, $poll->question);
            $this->renderJson(201, [
                'ok'        => true,
                'poll'      => $poll->toApiArray(),
                'join_url'  => $this->plugin->pollJoinUrl($poll->token),
                'short_url' => $shortUrl,
            ]);
        } catch (InvalidResponseException $e) {
            $this->renderJsonError(422, 'validation_failed', $e->getMessage());
        }
    }

    /* ─────────────────── Lifecycle endpoints ───────────────────── */

    /** POST /api/finish/{id} — `is_active := false` (Trails routes flat) */
    public function finish_action(string $pollId = ''): void
    {
        $this->withOwnedPoll($pollId, 'POST', function (Poll $poll, PollsService $service): void {
            $service->finishPoll($poll->id);
            $this->renderJson(200, ['ok' => true, 'is_active' => false]);
        });
    }

    /** POST /api/start/{id} — `is_active := true` (flat) */
    public function start_action(string $pollId = ''): void
    {
        $this->withOwnedPoll($pollId, 'POST', function (Poll $poll, PollsService $service): void {
            $service->startPoll($poll->id);
            $this->renderJson(200, ['ok' => true, 'is_active' => true]);
        });
    }

    /** POST /api/archive/{id} — `archived_at := now()` (flat) */
    public function archive_action(string $pollId = ''): void
    {
        $this->withOwnedPoll($pollId, 'POST', function (Poll $poll, PollsService $service): void {
            $service->archivePoll($poll->id);
            $this->renderJson(200, ['ok' => true, 'archived_at' => time()]);
        });
    }

    /** POST /api/unarchive/{id} — `archived_at := NULL` (flat) */
    public function unarchive_action(string $pollId = ''): void
    {
        $this->withOwnedPoll($pollId, 'POST', function (Poll $poll, PollsService $service): void {
            $service->unarchivePoll($poll->id);
            $this->renderJson(200, ['ok' => true, 'archived_at' => null]);
        });
    }

    /** DELETE /api/delete/{id} — hard delete incl. responses (flat) */
    public function delete_action(string $pollId = ''): void
    {
        $this->withOwnedPoll($pollId, 'DELETE', function (Poll $poll, PollsService $service): void {
            $service->deletePoll($poll->id);
            $this->renderJson(200, ['ok' => true, 'deleted' => $poll->id]);
        });
    }

    /* Restart runs as a Trails form (`workplace/restart_submit/{id}`), not a
       JSON API. The service methods `restartAsCompare`/`restartAsDuplicate`
       are used by the form handler. */

    /**
     * GET /api/compare_chain/{rootId} — peer instruction (flat).
     *
     * Returns the compare chain of a root poll (root + all follow-up rounds via
     * `parent_poll_id`) with aggregated counts per round. Owner auth is in the
     * service layer (`PollsService::loadCompareChain`) — foreign roots produce
     * `PollNotFoundException` (404, same as "not found", info-leakage protection).
     *
     * Response shape: see `CompareChain::toApiArray()`. No PII: counts are
     * aggregated, no user IDs in the response.
     */
    public function compare_chain_action(string $rootId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($rootId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Wurzel-Poll-ID fehlt.');
            return;
        }

        // Co-teaching: a tutor in the root poll's seminar may also see the
        // chain (consistent with poll_action/stream/export). The poll is loaded
        // only for the seminar-based tutor check; if it doesn't exist,
        // $allowTutor stays false → the service throws 404 (no leak).
        $rootPoll   = (new PollsRepository())->findById($rootId);
        $perm       = $GLOBALS['perm'] ?? null;
        $allowTutor = $rootPoll !== null && $rootPoll->seminarId !== null
            && $perm && $perm->have_studip_perm('tutor', $rootPoll->seminarId);

        try {
            $chain = $this->service->loadCompareChain($rootId, $userId, allowAnyOwner: $allowTutor);
            $this->renderJson(200, $chain->toApiArray());
        } catch (PollNotFoundException) {
            // Information-leakage protection: same 404 for "does not exist" and
            // "exists but belongs to someone else".
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
        }
    }

    /**
     * GET /api/freitext_responses/{pollId} (flat).
     *
     * Returns all free-text responses of a poll as a string array.
     * Auth: session + owner check.
     * No PII: only the `text` string, no user IDs or timestamps.
     *
     * Response: `{ "responses": ["text1", "text2", …] }`
     */
    public function freitext_responses_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        // Owner OR tutor in the associated seminar — consistent with
        // poll_action/stream_action/export_action (co-teaching sees the same
        // evaluation data). 404 instead of 403 (info-leak protection).
        $perm    = $GLOBALS['perm'] ?? null;
        if (!self::callerMayAccessPoll($poll, $userId, $perm)) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        if ($poll->type !== 'freitext') {
            $this->renderJsonError(422, 'wrong_type', 'Nur für Freitext-Abstimmungen verfügbar.');
            return;
        }

        $responses = $repo->findFreitextResponses($pollId);
        $this->renderJson(200, ['responses' => array_values($responses)]);
    }

    /**
     * GET /api/freitext_moderation/{pollId} — free-text responses WITH
     * response IDs for the moderation list. Owner/co-lecturers only; the
     * anonymous variant without IDs remains `freitext_responses_action`.
     */
    public function freitext_moderation_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;
        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }
        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        $perm = $GLOBALS['perm'] ?? null;
        if ($poll === null || !self::callerMayAccessPoll($poll, $userId, $perm)) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        if ($poll->type !== 'freitext') {
            $this->renderJsonError(422, 'wrong_type', 'Nur für Freitext-Abstimmungen verfügbar.');
            return;
        }
        $this->renderJson(200, ['responses' => $repo->findFreitextResponsesWithIds($pollId)]);
    }

    /**
     * POST /api/freitext_response_delete/{responseId} — removes a single
     * free-text response (post-moderation). The owner chain
     * response → poll → owner is checked by the service; CSRF as for all
     * lifecycle POSTs.
     */
    public function freitext_response_delete_action(string $responseId = ''): void
    {
        if (\Request::method() !== 'POST') {
            $this->renderJsonError(405, 'method_not_allowed', 'POST erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;
        if ($responseId === '') {
            $this->renderJsonError(400, 'missing_response_id', 'Response-ID fehlt.');
            return;
        }
        try {
            \CSRFProtection::verifyUnsafeRequest();
        } catch (\Throwable) {
            $this->renderJsonError(403, 'csrf_failed', 'CSRF-Token ungültig oder fehlt.');
            return;
        }
        try {
            $this->service->deleteFreitextResponse($responseId, $userId);
            $this->renderJson(200, ['ok' => true]);
        } catch (PollNotFoundException) {
            // 404 instead of 403 — don't reveal foreign response IDs as existing.
            $this->renderJsonError(404, 'response_not_found', 'Diese Antwort existiert nicht.');
        }
    }

    /**
     * GET /api/matrix_counts/{pollId} (flat).
     *
     * Returns aggregated responses of a matrix poll as a nested map:
     * `{ "counts": { "rowId": { "scaleId": count, … }, … } }`
     * Auth: session + owner check.
     */
    public function matrix_counts_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'GET') {
            $this->renderJsonError(405, 'method_not_allowed', 'GET erforderlich.');
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        $repo = new PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        // Owner OR tutor in the associated seminar — consistent with
        // poll_action/stream_action/export_action (co-teaching sees the same
        // evaluation data). 404 instead of 403 (info-leak protection).
        $perm    = $GLOBALS['perm'] ?? null;
        if (!self::callerMayAccessPoll($poll, $userId, $perm)) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        if ($poll->type !== 'matrix') {
            $this->renderJsonError(422, 'wrong_type', 'Nur für Matrix-Abstimmungen verfügbar.');
            return;
        }

        $counts = $repo->aggregateMatrixCountsForPoll($pollId);
        $this->renderJson(200, ['counts' => $counts]);
    }

    /* ────────────────────────────── Helper ────────────────────────────── */

    /**
     * Central access decision for evaluation endpoints: owner OR tutor level in
     * the associated seminar (co-teaching). Global polls (`seminarId === null`)
     * are visible to the owner only. Callers render a 404 (not 403) on `false`
     * — foreign poll IDs must not leak as "exists".
     *
     * `static` + perm parameter so it is purely testable (no DB/request state).
     *
     * @param object|null $perm Stud.IP `$GLOBALS['perm']` with `have_studip_perm()`
     */
    private static function callerMayAccessPoll(Poll $poll, string $userId, ?object $perm): bool
    {
        if ($poll->userId === $userId) {
            return true;
        }
        return $poll->seminarId !== null
            && $perm !== null
            && $perm->have_studip_perm('tutor', $poll->seminarId);
    }

    /**
     * Returns the logged-in lecturer's user ID — or renders a 401 response and
     * returns `null`. For API endpoints that require a logged-in user.
     */
    private function requireOwnerUserId(): ?string
    {
        $userId = (string) (($GLOBALS['user']->id) ?? '');
        if ($userId === '' || $userId === 'nobody') {
            $this->renderJsonError(401, 'unauthorized', 'Anmeldung erforderlich.');
            return null;
        }
        return $userId;
    }

    /**
     * Standardized lifecycle-endpoint wrapper:
     *   - HTTP method check
     *   - poll lookup (404 otherwise)
     *   - owner check (`$user->id === $poll->userId`) — no tutor-perm fallback,
     *     since lifecycle operations are explicitly owner actions
     *   - CSRF check via Stud.IP
     *
     * Central auth layer so no endpoint accidentally skips a check.
     */
    private function withOwnedPoll(string $pollId, string $method, callable $fn): void
    {
        if (\Request::method() !== $method) {
            $this->renderJsonError(405, 'method_not_allowed', sprintf('%s erforderlich.', $method));
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($pollId === '') {
            $this->renderJsonError(400, 'missing_poll_id', 'Poll-ID fehlt.');
            return;
        }

        try {
            \CSRFProtection::verifyUnsafeRequest();
        } catch (\Throwable) {
            $this->renderJsonError(403, 'csrf_failed', 'CSRF-Token ungültig oder fehlt.');
            return;
        }

        $poll = $this->service->findPollById($pollId);
        if ($poll === null) {
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }
        if (!self::callerMayAccessPoll($poll, $userId, $GLOBALS['perm'] ?? null)) {
            // We return 404 instead of 403 so foreign poll IDs cannot be
            // detected as "exists" via status-code discrimination
            // (info-leakage protection). Co-teaching: tutors in the poll's
            // seminar may control it (callerMayAccessPoll).
            $this->renderJsonError(404, 'poll_not_found', 'Diese Abstimmung existiert nicht.');
            return;
        }

        $fn($poll, $this->service);
    }

    /**
     * Collection counterpart of `withOwnedPoll`: method check, session, CSRF,
     * lookup + access check owner OR tutor in the collection's assigned course
     * (co-teaching). Course-independent collections (seminarId === null) stay
     * owner-only. 404 instead of 403 for foreign IDs (info-leakage protection).
     * `seminarId` comes from the DB (fail-closed).
     */
    private function withOwnedCollection(string $collectionId, string $method, callable $fn): void
    {
        if (\Request::method() !== $method) {
            $this->renderJsonError(405, 'method_not_allowed', sprintf('%s erforderlich.', $method));
            return;
        }
        $userId = $this->requireOwnerUserId();
        if ($userId === null) return;

        if ($collectionId === '') {
            $this->renderJsonError(400, 'missing_collection_id', 'Sammlungs-ID fehlt.');
            return;
        }

        try {
            \CSRFProtection::verifyUnsafeRequest();
        } catch (\Throwable) {
            $this->renderJsonError(403, 'csrf_failed', 'CSRF-Token ungültig oder fehlt.');
            return;
        }

        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new PollsRepository(),
        );
        $collection = $service->findCollectionById($collectionId);
        $mayTutor   = $collection !== null
            && $collection->seminarId !== null
            && $this->mayWriteSeminar($collection->seminarId);
        if ($collection === null || ($collection->userId !== $userId && !$mayTutor)) {
            $this->renderJsonError(404, 'collection_not_found', 'Diese Sammlung existiert nicht.');
            return;
        }

        $fn($collection, $service);
    }

    private function renderJson(int $status, array $data): void
    {
        $this->set_status($status);
        $this->set_content_type('application/json; charset=utf-8');
        $this->render_text(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function renderJsonError(int $status, string $code, string $message): void
    {
        $this->renderJson($status, ['error' => $code, 'message' => $message]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function decodeJsonBody(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }
        // Upper bound against bloated payloads (a response is a slim JSON
        // object; 64 KB is generous even for matrix).
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private const MAX_BODY_BYTES = 65_536;

    /**
     * Same-origin protection for state-changing requests without a session
     * token (anonymous polls-app). Compares the `Origin` header (or `Referer`
     * as fallback) with the current request host.
     *
     * Fail-closed: if a matching origin cannot be established — missing host,
     * missing Origin AND Referer, or an unparseable Origin — the request is
     * rejected. Real browsers always send an `Origin` header on a `fetch`
     * POST (even same-origin), so legitimate vote clients are unaffected;
     * cross-site forged requests without Origin are blocked instead of let
     * through.
     */
    private static function isSameOrigin(): bool
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return false; // no comparison possible without a host — fail closed
        }

        $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($source === '') {
            $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        }
        if ($source === '') {
            return false; // no Origin/Referer supplied — fail closed
        }

        $originHost = parse_url($source, PHP_URL_HOST);
        if (!is_string($originHost) || $originHost === '') {
            return false;
        }
        // Strip the port from the host header, then compare case-insensitively.
        $requestHost = strtolower((string) preg_replace('/:\d+$/', '', $host));
        return strtolower($originHost) === $requestHost;
    }
}
