<?php

declare(strict_types=1);

/**
 * Quorum workplace admin full page.
 *
 * Stud.IP plugin convention: Trails controller under `controllers/`, class
 * extends `PluginController`. URL form:
 *   /plugins.php/quorumstudipplugin/workplace/index
 *
 * Mounts the `workplace-app` Vue 3 bundle under
 * `<div id="quorum-workplace-app">`. Reached via the Stud.IP navigation item
 * (`Navigation::addItem('/contents/quorum', …)`).
 *
 * Auth: requires the configured minimum role (default `dozent`).
 */
class WorkplaceController extends PluginController
{
    use \Quorum\Controllers\CourseFrameTrait;
    use \Quorum\Controllers\SeminarAccessTrait;
    use \Quorum\Controllers\UploadDefinitionTrait;
    use \Quorum\Controllers\ManualDownloadTrait;

    /**
     * Course ID when the form comes from the course tab (cid set AND tutor
     * right in the course). The page then renders in the course frame instead
     * of the workplace. null = global workplace context.
     */
    private ?string $courseId = null;

    public function before_filter(&$action, &$args): void
    {
        parent::before_filter($action, $args);

        // `assetBaseUrl` is the direct filesystem path for static assets
        // (CSS/JS bundles, sprite icons), served by the webserver without the
        // Trails dispatcher. The views rely on it
        // (`<link href="$assetBaseUrl/public/$css">`). `pluginUrl` (set in the
        // action methods) is the Trails path for API/routes.
        $this->assetBaseUrl = $this->plugin->getPluginURL();

        $perm = $GLOBALS['perm'] ?? null;
        $cid  = trim((string) \Request::option('cid', ''));

        // Helpbar context: the index page carries its filter in the `?view`
        // param. The archive (`?view=archive`) gets its own help text via this
        // instead of the generic overview text of the active view.
        $helpKey = $action;
        if ($action === 'index' && \Request::option('view') === 'archive') {
            $helpKey = 'archive';
        }

        // Course context: called from the course tab (cid set) with tutor right
        // IN the course. Then the course frame (header/tabs/sidebar) is
        // activated instead of the workplace frame, and the course-bound tutor
        // right suffices (consistent with the course-app, where tutors may
        // manage surveys), not the global minimum role. Owner/CSRF checks in
        // the actions are unaffected.
        if ($cid !== '' && $perm && $perm->have_studip_perm('tutor', $cid)) {
            $this->courseId = $cid;
            $this->activateCourseFrame();
            // Per-view help: `course/<action>` automatically falls back to
            // `workplace/<action>` for the shared form views.
            \Quorum\AttributionHelper::addToHelpbar('course/' . $helpKey);
            return;
        }

        // Global workplace: the minimum role is admin-configurable via
        // `Config::get()->QUORUM_MIN_ROLE` (default: dozent).
        $minRole = \QuorumStudipPlugin::minRole();
        if (!$perm || !$perm->have_perm($minRole)) {
            throw new AccessDeniedException(_quorum('Sie haben keine Berechtigung, den Quorum-Arbeitsplatz zu öffnen.'));
        }

        // Stud.IP layout frame with its own page title
        \PageLayout::setTitle(_quorum('Meine Quorum-Umfragen'));

        // Force the workplace frame: the tile/tab lives under `/contents/quorum`
        // (see QuorumStudipPlugin::registerWorkplaceTile). Only activating
        // exactly this item makes Stud.IP draw the "My workplace" header (active
        // workplace icon) incl. the tab bar. `activateItem` may throw (e.g. tile
        // not registered due to role) — the workplace then stays functional,
        // only without the header highlight.
        try {
            \Navigation::activateItem('/contents/quorum');
        } catch (\InvalidArgumentException) {
            // Tile not registered — workplace loads anyway, without highlight.
        }

        // Context help (per view, e.g. `workplace/new`) + GPLv3 §7
        // attribution in the helpbar. In the workplace additionally the entry
        // point for loading the demo content (onboarding via the helpbar).
        \Quorum\AttributionHelper::addToHelpbar(
            'workplace/' . $helpKey,
            \PluginEngine::getURL($this->plugin, [], 'workplace/load_demo', true),
            \PluginEngine::getURL($this->plugin, [], 'workplace/manual', true),
        );
    }

    public function index_action(): void
    {
        $this->mountWorkplaceBundle();

        // ?view=active|archive — becomes the active sidebar entry and is passed
        // to the Vue app so the initial state matches the filter.
        $view = (string) (\Request::option('view') ?: 'active');
        if (!in_array($view, ['active', 'archive'], true)) {
            $view = 'active';
        }
        $this->view = $view;

        $this->buildSidebar($view);
    }

    /**
     * GET /workplace/results/{pollId} — retrospective results view.
     *
     * Review of any own poll (running/paused/archived): chart per question
     * type + CSV export + definition download — without a course binding and
     * without a presenter detour (per user feedback). The Vue component loads
     * the data via `GET /api/poll/{id}` (owner-authorized); here only the
     * owner check + mount.
     */
    public function results_action(string $pollId = ''): void
    {
        $userId  = $this->ownerOrAccessDenied();
        $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
        $poll    = $service->findPollById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        $this->mountWorkplaceBundle();
        $this->pollId = $poll->id;
        // CSRF for the free-text moderation (POST deletion of single responses).
        $this->csrf   = \CSRFProtection::token();

        \PageLayout::setTitle(_quorum('Ergebnisse'));
        // Inside the course frame (opened from the course tab) the course
        // sidebar stays in place — so the results detail view in a course is
        // the same as on the workplace, only within the course frame.
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, $poll->isArchived() ? 'archive' : 'active');
        } else {
            $this->buildSidebar($poll->isArchived() ? 'archive' : 'active');
        }
    }

    /**
     * GET /workplace/new — create form (full page, not a dialog).
     *
     * Plain PHP form with Stud.IP standard markup (`<form class="default">`,
     * `<input>`/`<textarea>`, `<button class="button">`). Opened as a full page
     * (no asDialog()) so Stud.IP widgets (QuickSearch, autocomplete) initialize
     * correctly.
     *
     * The submit goes to `workplace/create_action` (POST), which calls
     * `PollsService::createPoll` and redirects to the workplace index (or
     * re-renders the form on a validation error).
     */
    public function new_action(): void
    {
        // `cid` from the URL: comes from the course-tab bridge link
        // (SurveysIndex.vue → window.location). When set, the course selector
        // is hidden in the form and the binding is sent as a hidden field.
        $cid = trim((string) \Request::option('cid', ''));

        // `collection` from the URL: direct creation from within a collection
        // (collection detail page → "Create new survey"). The new poll is
        // assigned to the collection right after creation — no detour via the
        // poll list plus "Add to collection" (user feedback). Ownership is
        // checked here AND on submit.
        $collection = null;
        $collectionId = trim((string) \Request::option('collection', ''));
        if ($collectionId !== '' && $this->courseId === null) {
            $userId     = $this->ownerOrAccessDenied();
            $collection = $this->ownedCollectionOr404($collectionId, $userId);
        }

        // Sidebar: in course context the course sidebar (frame stays the course
        // tab), otherwise the workplace sidebar.
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, 'form');
        } else {
            $this->buildSidebar($collection !== null ? 'collections' : 'active');
        }
        $this->renderCreateForm(
            question:  '',
            options:   [['label' => ''], ['label' => '']],
            seminarId: $cid !== '' ? $cid : null,
            error:     null,
            seminarLocked: $cid !== '',
            type: 'mc',
            collectionId:   $collection?->id,
            collectionName: $collection->name ?? '',
        );
    }

    /**
     * POST /workplace/create — form handler for `new_action`.
     *
     * Validates server-side (`PollsService::createPoll` throws
     * `InvalidResponseException` on empty question, < 2 options, …). On success:
     * redirect to the workplace index. On error: re-render the form.
     */
    public function create_action(): void
    {
        $userId = $this->requireFormPoster();
        \CSRFProtection::verifyUnsafeRequest();

        $input = $this->parseCreateInput();
        // The `seminar_id` hidden field is client-controlled — binding to a
        // seminar requires tutor rights there (otherwise surveys could be
        // injected into foreign courses).
        if (!$this->mayWriteSeminar($input['seminarId'])) {
            throw new \AccessDeniedException(_quorum('Sie haben keine Berechtigung für diese Veranstaltung.'));
        }

        $seminarId = $input['seminarId'];
        $service   = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
        try {
            $poll = $service->createPoll(
                userId:          $userId,
                question:        $input['question'],
                type:            $input['type'],
                options:         $input['options'],
                seminarId:       $seminarId,
                durationSeconds: $input['duration'],
                quizMode:        $input['quizMode'],
                resultsPublic:   $input['resultsPublic'],
            );
            // Register short link (best-effort → "My short links").
            $this->plugin->registerPollShortLink($poll->token, $userId, $poll->question);

            // Direct creation from within a collection (hidden field
            // `collection_id`): assign the new poll right away and jump back
            // to the collection. The service checks ownership of both the
            // poll and the collection (defense-in-depth).
            $collectionId = trim((string) \Request::get('collection_id'));
            if ($collectionId !== '' && $this->redirectAfterCollectionAdd($collectionId, $poll->id, $userId)) {
                return;
            }

            \PageLayout::postSuccess(_quorum('Umfrage angelegt.'));
            // Created course-bound (e.g. via the course tab) → back to the
            // course tab instead of the global workplace. Course-independent
            // surveys still land in the workplace.
            $this->redirect(
                $seminarId !== null
                    ? \PluginEngine::getURL($this->plugin, ['cid' => $seminarId], 'index/index', false)
                    : \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false)
            );
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            // Stud.IP notification + redirect back to the form. All user
            // feedback goes through Stud.IP's PageLayout notification bar.
            \PageLayout::postError($e->getMessage());
            $this->redirect(\PluginEngine::getURL($this->plugin, $this->createErrorRedirectParams($seminarId), 'workplace/new', false));
        }
    }

    /**
     * Shared entry guard of the form handlers (create/update/restart_submit):
     * enforce POST, require a logged-in user. Returns the user id or throws
     * `AccessDeniedException` (identical messages as previously inline).
     */
    private function requireFormPoster(): string
    {
        if (\Request::method() !== 'POST') {
            throw new \AccessDeniedException(_quorum('POST erforderlich.'));
        }
        $userId = (string) ($GLOBALS['user']->id ?? '');
        if ($userId === '' || $userId === 'nobody') {
            throw new \AccessDeniedException(_quorum('Anmeldung erforderlich.'));
        }
        return $userId;
    }

    /**
     * Parses the create form fields into a normalized array. Pure
     * request→array translation without side effects; validation stays in the
     * service.
     *
     * @return array{question: string, type: string, options: list<array{label: string, correct: bool}>, seminarId: ?string, duration: ?int, quizMode: bool, resultsPublic: bool}
     */
    private function parseCreateInput(): array
    {
        $typeIn    = trim((string) \Request::get('type', 'mc'));
        // Only the types supported by the flat create form (no matrix — see
        // PollType::SIMPLE_FORM); unknown values degrade safely to `mc` instead
        // of creating a structurally broken poll.
        $type      = in_array($typeIn, \Quorum\Polls\PollType::SIMPLE_FORM, true) ? $typeIn : 'mc';
        $optionsIn = (array)  \Request::getArray('options');
        $seminarId = trim((string) \Request::get('seminar_id'));
        $seminarId = $seminarId === '' ? null : $seminarId;
        // Optional time limit (minutes input → seconds). Empty/0 = no limit.
        $durationMin = (int) \Request::get('duration_minutes');
        // Correct markers (checkbox values = option position) and quiz opt-in.
        // The service enables quiz mode only for single choice with at least
        // one correct option.
        $correctIdx = array_map('intval', (array) \Request::getArray('options_correct'));
        // Scale: numeric (point count 2–5) OR named steps (labels from the
        // option fields). `scale_mode` decides; anything else → numeric.
        $scalePoints = (int) \Request::get('scale_points', 5);
        $scaleMode   = \Request::get('scale_mode') === 'named' ? 'named' : 'numeric';

        return [
            'question'      => (string) \Request::get('question'),
            'type'          => $type,
            'options'       => $this->buildCreateOptions($type, $optionsIn, $correctIdx, $scalePoints, $scaleMode),
            'seminarId'     => $seminarId,
            'duration'      => $durationMin > 0 ? $durationMin * 60 : null,
            'quizMode'      => \Request::int('quiz_mode', 0) === 1,
            // Opt-out: defaults to public. The form sends a hidden 0 before the
            // checkbox (1) — if the field is missing entirely, it counts as
            // public (default 1).
            'resultsPublic' => \Request::int('results_public', 1) === 1,
        ];
    }

    /**
     * Converts the flat form options into the service format
     * `[{label, correct}, …]`. Free-text has no options → empty array.
     *
     * @param array<int|string, mixed> $optionsIn
     * @param list<int>                $correctIdx
     * @return list<array{label: string, correct: bool}>
     */
    private function buildCreateOptions(string $type, array $optionsIn, array $correctIdx, int $scalePoints, string $scaleMode): array
    {
        if ($type === 'freitext') {
            return [];
        }
        // Numeric scale: N scale points (2–5) generated server-side as
        // "1" … "N"; no quiz (`correct` always false). A NAMED scale falls
        // through on purpose and uses the entered step labels like an ordinary
        // option list (below).
        if ($type === 'scales' && $scaleMode !== 'named') {
            $n = max(2, min(5, $scalePoints));
            return array_map(
                static fn (int $i): array => ['label' => (string) ($i + 1), 'correct' => false],
                range(0, $n - 1)
            );
        }
        $options = [];
        foreach ($optionsIn as $pos => $label) {
            $options[] = [
                'label'   => (string) $label,
                'correct' => in_array((int) $pos, $correctIdx, true),
            ];
        }
        return $options;
    }

    /**
     * Direct creation from a collection: assigns the new poll and jumps back.
     * Always returns `true` (a redirect was sent) so the caller can end the
     * action.
     */
    private function redirectAfterCollectionAdd(string $collectionId, string $pollId, string $userId): bool
    {
        $collections = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        try {
            $collections->addPollToCollection($collectionId, $pollId, $userId);
            \PageLayout::postSuccess(_quorum('Umfrage angelegt und zur Sammlung hinzugefügt.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, [], 'workplace/collection/' . $collectionId, false));
        } catch (\Quorum\Polls\Exceptions\PollNotFoundException) {
            // Collection vanished/foreign in the meantime: the poll still
            // exists — no rollback, just an honest message.
            \PageLayout::postWarning(_quorum('Umfrage angelegt — die Sammlung wurde jedoch nicht gefunden.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false));
        }
        return true;
    }

    /**
     * Builds the query params for the error redirect back to the create form,
     * so cid (course frame) and collection (direct-in-collection creation)
     * are preserved.
     *
     * @return array<string, string>
     */
    private function createErrorRedirectParams(?string $seminarId): array
    {
        $params = $seminarId !== null ? ['cid' => $seminarId] : [];
        $formCollectionId = trim((string) \Request::get('collection_id'));
        if ($formCollectionId !== '') {
            $params['collection'] = $formCollectionId;
        }
        return $params;
    }

    /**
     * Renders the create form. Validation errors are delivered only via
     * `PageLayout::postError()` (Stud.IP notification bar), not inline.
     *
     * @param list<array{label: string}> $options
     */
    private function renderCreateForm(string $question, array $options, ?string $seminarId, ?string $error = null, bool $seminarLocked = false, string $type = 'mc', ?string $collectionId = null, string $collectionName = ''): void
    {
        $this->question      = $question;
        $this->type          = $type;
        $this->options       = $options;
        $this->seminarId     = (string) ($seminarId ?? '');
        $this->seminarLocked = $seminarLocked;
        // Direct creation into a collection: target as hidden field, name as
        // info line in the hero (see views/workplace/new.php).
        $this->collectionId   = (string) ($collectionId ?? '');
        $this->collectionName = $collectionName;
        // Quiz checkbox state (form re-render; defaults to off).
        $this->quizMode       = false;
        // When the course binding is already fixed (course-tab bridge), the
        // view shows the course name instead of the QuickSearch and writes the
        // ID into the form as a hidden field.
        $this->seminarName   = '';
        if ($seminarLocked && $this->seminarId !== '') {
            $sem = \Course::find($this->seminarId);
            $this->seminarName = $sem ? (string) $sem->name : $this->seminarId;
        }
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->actionUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/create', false);
        // Cancel URL: back to where the user came from — collection detail
        // for direct-in-collection creation, course tab with a locked seminar,
        // otherwise the workplace index.
        if ($this->collectionId !== '') {
            $this->cancelUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/collection/' . $this->collectionId, false);
        } elseif ($seminarLocked && $this->seminarId !== '') {
            $this->cancelUrl = \PluginEngine::getURL($this->plugin, ['cid' => $this->seminarId], 'index/index', false);
        } else {
            $this->cancelUrl = \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false);
        }

        // Push the workplace-app token CSS into `<head>` — contains Aurora
        // tokens + form styles. Stud.IP form CSS comes from the PageLayout itself.
        $this->pluginUrl   = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Neue Umfrage anlegen'));
        // Full Stud.IP PageLayout frame — ensures correct form CSS
        // (`<form class="default">` etc.) and Quorum bundle paths. $this->layout
        // (Stud.IP base layout, set in before_filter) MUST be passed, otherwise
        // Trails renders the bare fragment without the Stud.IP frame/core CSS.
        $this->render_template('workplace/new', $this->layout);
    }

    /**
     * GET /workplace/edit/{pollId} — edit form.
     *
     * Plain PHP form like `new_action`. Options are locked (readonly + hint)
     * once the poll has responses — otherwise existing responses would become
     * invalid. Question and course binding are always editable.
     *
     * Owner check + 404 for foreign access (information-leakage protection).
     */
    public function edit_action(string $pollId = ''): void
    {
        $userId  = (string) ($GLOBALS['user']->id ?? '');
        $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
        $poll    = $service->findPollById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, 'form');
        } else {
            $this->buildSidebar('active');
        }
        $repo = new \Quorum\Polls\PollsRepository();
        $this->renderEditForm(
            poll:           $poll,
            question:       $poll->question,
            options:        $poll->options,
            seminarId:      $poll->seminarId,
            optionsLocked:  $repo->countResponses($poll->id) > 0,
        );
    }

    /**
     * POST /workplace/update/{pollId} — form handler for `edit_action`.
     */
    public function update_action(string $pollId = ''): void
    {
        $userId = $this->requireFormPoster();
        \CSRFProtection::verifyUnsafeRequest();

        $repo    = new \Quorum\Polls\PollsRepository();
        $service = new \Quorum\Polls\PollsService($repo);
        $poll    = $service->findPollById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }

        $question  = (string) \Request::get('question');
        $seminarId = trim((string) \Request::get('seminar_id'));
        $seminarId = $seminarId === '' ? null : $seminarId;
        // Rebinding to a seminar requires tutor rights there.
        if (!$this->mayWriteSeminar($seminarId)) {
            throw new \AccessDeniedException(_quorum('Sie haben keine Berechtigung für diese Veranstaltung.'));
        }
        $hasResponses = $repo->countResponses($poll->id) > 0;
        // Result visibility (opt-out) — changeable at any time, including with responses.
        $resultsPublic = \Request::int('results_public', 1) === 1;

        // Options are untouched once responses exist — the form submits them
        // readonly, and the controller explicitly ignores them here
        // (defense-in-depth in case someone tampers with the form).
        $options = $this->buildUpdateOptions($poll, $hasResponses);

        try {
            $service->updatePoll(
                pollId:        $poll->id,
                question:      $question,
                options:       $options,
                seminarId:     $seminarId,
                resultsPublic: $resultsPublic,
            );
            \PageLayout::postSuccess(_quorum('Umfrage aktualisiert.'));
            // Course-bound → back to the course tab, otherwise the workplace.
            $this->redirect(
                $seminarId !== null
                    ? \PluginEngine::getURL($this->plugin, ['cid' => $seminarId], 'index/index', false)
                    : \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false)
            );
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            // Stud.IP notification instead of inline error. Re-show via redirect
            // to the edit URL (Stud.IP's standard form-error workflow). Carry
            // cid so the re-rendered form stays in the course frame.
            \PageLayout::postError($e->getMessage());
            $this->redirect(\PluginEngine::getURL(
                $this->plugin,
                $poll->seminarId !== null ? ['cid' => $poll->seminarId] : [],
                'workplace/edit/' . $poll->id,
                false
            ));
        }
    }

    /**
     * Builds the options list for `updatePoll`. With responses already present
     * it stays `null` (options unchanged); otherwise the form labels are
     * joined with the existing index IDs.
     *
     * @return list<array{id: ?string, label: string}>|null
     */
    private function buildUpdateOptions(\Quorum\Polls\Poll $poll, bool $hasResponses): ?array
    {
        if ($hasResponses) {
            return null;
        }
        $optionsIn = (array) \Request::getArray('options');
        $options   = [];
        foreach ($optionsIn as $i => $label) {
            // Preserve the existing option ID (index-based)
            $existing  = $poll->options[$i]['id'] ?? null;
            $options[] = ['id' => $existing, 'label' => (string) $label];
        }
        return $options;
    }

    /**
     * Renders the edit form. As with the create form, validation errors are
     * delivered only via `PageLayout::postError()` (Stud.IP notification bar).
     *
     * @param list<array{id?: string, label: string}> $options
     */
    private function renderEditForm(
        \Quorum\Polls\Poll $poll,
        string $question,
        array $options,
        ?string $seminarId,
        bool $optionsLocked,
    ): void {
        $this->poll          = $poll;
        $this->question      = $question;
        $this->options       = $options;
        $this->seminarId     = (string) ($seminarId ?? '');
        $this->optionsLocked = $optionsLocked;
        $this->resultsPublic = $poll->resultsPublic;
        $this->csrf          = \CSRFProtection::tokenTag();
        $this->actionUrl     = \PluginEngine::getURL($this->plugin, [], 'workplace/update/' . $poll->id, false);
        // Cancel: from the course tab back to the course, otherwise the workplace.
        $this->cancelUrl     = $this->courseId !== null
            ? \PluginEngine::getURL($this->plugin, ['cid' => $this->courseId], 'index/index', false)
            : \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false);

        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Umfrage bearbeiten'));
        $this->render_template('workplace/edit', $this->layout);
    }

    /**
     * GET /workplace/restart/{pollId} — restart form (compare/duplicate choice).
     *
     * Plain PHP form like `new_action` and `edit_action` — Stud.IP standard
     * markup, mode choice as two radio cards, Aurora hero header previewing the
     * original question. Submit goes to `restart_submit_action`.
     */
    public function restart_action(string $pollId = ''): void
    {
        $userId = (string) ($GLOBALS['user']->id ?? '');
        $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
        $poll = $service->findPollById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            // Information-leakage protection: 404 instead of 403 for foreign IDs.
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, 'form');
        } else {
            $this->buildSidebar('active');
        }
        $this->renderRestartForm($poll, mode: 'compare');
    }

    /**
     * POST /workplace/restart_submit/{pollId} — form handler for `restart_action`.
     *
     * Validates the mode (`compare`|`duplicate`), calls the service, redirects
     * to the workplace index. On error: Stud.IP notification bar + redirect to
     * the restart form.
     */
    public function restart_submit_action(string $pollId = ''): void
    {
        $userId = $this->requireFormPoster();
        \CSRFProtection::verifyUnsafeRequest();

        $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
        $poll = $service->findPollById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }

        try {
            $this->applyRestartMode($service, $poll, (string) \Request::get('mode'));
            \PageLayout::postSuccess(_quorum('Umfrage erneut gestartet.'));
            // Course-bound → back to the course tab, otherwise the workplace.
            $this->redirect(
                $poll->seminarId !== null
                    ? \PluginEngine::getURL($this->plugin, ['cid' => $poll->seminarId], 'index/index', false)
                    : \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false)
            );
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            \PageLayout::postError($e->getMessage());
            $this->redirect(\PluginEngine::getURL(
                $this->plugin,
                $poll->seminarId !== null ? ['cid' => $poll->seminarId] : [],
                'workplace/restart/' . $poll->id,
                false
            ));
        }
    }

    /**
     * Runs the chosen restart mode. An unknown mode throws
     * `InvalidResponseException` (turned into a notification by the caller).
     */
    private function applyRestartMode(\Quorum\Polls\PollsService $service, \Quorum\Polls\Poll $poll, string $mode): void
    {
        if ($mode === 'compare') {
            $service->restartAsCompare($poll->id);
        } elseif ($mode === 'duplicate') {
            $service->restartAsDuplicate($poll->id);
        } else {
            throw new \Quorum\Polls\Exceptions\InvalidResponseException(
                _quorum('Bitte wählen Sie einen Modus (Vergleichen oder Duplizieren).')
            );
        }
    }

    /**
     * Renders the restart form. Validation errors come via
     * `PageLayout::postError` (Stud.IP notification bar), not inline.
     */
    private function renderRestartForm(\Quorum\Polls\Poll $poll, string $mode = 'compare'): void
    {
        $repo = new \Quorum\Polls\PollsRepository();
        $this->poll          = $poll;
        $this->responseCount = $repo->countResponses($poll->id);
        $this->mode          = in_array($mode, ['compare', 'duplicate'], true) ? $mode : 'compare';
        $this->csrf          = \CSRFProtection::tokenTag();
        $this->actionUrl     = \PluginEngine::getURL($this->plugin, [], 'workplace/restart_submit/' . $poll->id, false);
        // Cancel: from the course tab back to the course, otherwise the workplace.
        $this->cancelUrl     = $this->courseId !== null
            ? \PluginEngine::getURL($this->plugin, ['cid' => $this->courseId], 'index/index', false)
            : \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false);

        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Umfrage erneut starten'));
        $this->render_template('workplace/restart', $this->layout);
    }

    /* ───────────────── Import (file) ───────────────── */

    /**
     * GET /workplace/import_file — "import survey" in the workplace: upload form
     * for a Quorum survey definition (`.json`). The survey is created
     * course-independent in the workplace (seminarId = null).
     */
    public function import_file_action(): void
    {
        $this->buildSidebar('active');
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->actionUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/import_file_submit', false);
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Umfrage importieren'));
        $this->render_template('index/import_file', $this->layout);
    }

    /** POST /workplace/import_file_submit — creates the imported survey (course-independent). */
    public function import_file_submit_action(): void
    {
        \CSRFProtection::verifyUnsafeRequest();
        $userId = (string) ($GLOBALS['user']->id ?? '');
        $back   = \PluginEngine::getURL($this->plugin, [], 'workplace/import_file', false);

        try {
            $def     = \Quorum\Polls\SurveyDefinition::fromJson($this->readUploadedDefinition('definition'));
            $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
            $poll    = $service->createPoll(
                userId:    $userId,
                question:  $def['question'],
                type:      $def['type'],
                options:   $def['options'],
                seminarId: null,
            );
            $this->plugin->registerPollShortLink($poll->token, $userId, $poll->question);
            \PageLayout::postSuccess(_quorum('Umfrage importiert.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false));
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            \PageLayout::postError($e->getMessage());
            $this->redirect($back);
        } catch (\Throwable $e) {
            // Don't let structurally broken files surface as a 500/stack trace.
            \PageLayout::postError(_quorum('Die Datei konnte nicht importiert werden (ungültiges Format).'));
            $this->redirect($back);
        }
    }

    /**
     * GET /workplace/load_demo — "load demo content": confirmation page that
     * explains what gets created (example polls per question type + one demo
     * collection, all into the own archive). Showcase material; can be
     * reactivated and remodeled.
     */
    public function load_demo_action(): void
    {
        $this->buildSidebar('archive');
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->actionUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/load_demo_submit', false);
        $this->cancelUrl = \PluginEngine::getURL($this->plugin, ['view' => 'archive'], 'workplace/index', false);
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Demo-Inhalte laden'));
        $this->render_template('workplace/load_demo', $this->layout);
    }

    /**
     * POST /workplace/load_demo_submit — creates the example content (own
     * archive). Idempotent: if the demos already exist, nothing happens.
     */
    public function load_demo_submit_action(): void
    {
        \CSRFProtection::verifyUnsafeRequest();
        $userId = (string) ($GLOBALS['user']->id ?? '');
        $back   = \PluginEngine::getURL($this->plugin, ['view' => 'archive'], 'workplace/index', false);

        $pollsRepo       = new \Quorum\Polls\PollsRepository();
        $collectionsRepo = new \Quorum\Polls\CollectionsRepository();
        $seeder = new \Quorum\Demo\DemoContentSeeder(
            new \Quorum\Polls\PollsService($pollsRepo),
            $pollsRepo,
            new \Quorum\Polls\CollectionsService($collectionsRepo, $pollsRepo),
            $collectionsRepo,
        );

        $db = \DBManager::get();
        $db->beginTransaction();
        try {
            $result = $seeder->seedFor($userId);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            \PageLayout::postError(_quorum('Die Demo-Inhalte konnten nicht geladen werden.'));
            $this->redirect($back);
            return;
        }

        if ($result['alreadyLoaded']) {
            \PageLayout::postInfo(_quorum('Die Demo-Inhalte wurden bereits geladen — Sie finden sie im Archiv.'));
        } else {
            \PageLayout::postSuccess(sprintf(
                _quorum('%d Demo-Umfragen und eine Demo-Sammlung wurden in Ihr Archiv geladen.'),
                $result['pollCount'],
            ));
        }
        $this->redirect($back);
    }

    /**
     * GET /workplace/manual — downloads the complete Quorum manual as a Stud.IP
     * PDF (teacher scope, session language). Linked from the helpbar
     * ("Anleitung herunterladen").
     */
    public function manual_action(): void
    {
        $this->sendManualPdf(\Quorum\Manual\ManualService::AUDIENCE_TEACHER);
    }

    /**
     * GET /workplace/import_collection — "import collection": upload form for a
     * Quorum collection definition (`.json`). Collection + all member surveys
     * are created fresh (course-independent).
     */
    public function import_collection_action(): void
    {
        $this->buildSidebar('collections');
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->actionUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/import_collection_submit', false);
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Sammlung importieren'));
        $this->render_template('workplace/import_collection', $this->layout);
    }

    /** POST /workplace/import_collection_submit — creates the collection + member surveys. */
    public function import_collection_submit_action(): void
    {
        \CSRFProtection::verifyUnsafeRequest();
        $userId = (string) ($GLOBALS['user']->id ?? '');
        $back   = \PluginEngine::getURL($this->plugin, [], 'workplace/import_collection', false);

        $db = \DBManager::get();
        try {
            // Parse + file validation BEFORE any write.
            $def         = \Quorum\Polls\CollectionDefinition::fromJson($this->readUploadedDefinition('definition'));
            $pollsRepo   = new \Quorum\Polls\PollsRepository();
            $pollsSvc    = new \Quorum\Polls\PollsService($pollsRepo);
            $collSvc     = new \Quorum\Polls\CollectionsService(new \Quorum\Polls\CollectionsRepository(), $pollsRepo);

            // Collection + all members atomically. If one survey fails mid-import
            // (e.g. an empty question only caught in createPoll), EVERYTHING is
            // rolled back — no half collection remains.
            $db->beginTransaction();
            $collection = $collSvc->createCollection($userId, $def['name'], $def['description']);
            $position   = 0;
            $shortLinks = [];
            foreach ($def['surveys'] as $survey) {
                $poll = $pollsSvc->createPoll(
                    userId:    $userId,
                    question:  $survey['question'],
                    type:      $survey['type'],
                    options:   $survey['options'],
                    seminarId: null,
                );
                $collSvc->addPollToCollection($collection->id, $poll->id, $userId, $position++);
                $shortLinks[] = [$poll->token, $poll->question];
            }
            $db->commit();

            // Short links only AFTER the commit (best-effort, own table — must
            // not roll back the import if registration fails).
            foreach ($shortLinks as [$token, $question]) {
                $this->plugin->registerPollShortLink($token, $userId, $question);
            }
            \PageLayout::postSuccess(_quorum('Sammlung importiert.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, [], 'workplace/collection/' . $collection->id, false));
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            \PageLayout::postError($e->getMessage());
            $this->redirect($back);
        } catch (\Throwable $e) {
            // Don't let broken JSON surface as a 500/stack trace.
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            \PageLayout::postError(_quorum('Die Sammlung konnte nicht importiert werden (ungültiges Format).'));
            $this->redirect($back);
        }
    }

    /* ───────────────── private mounting helpers ───────────────── */

    private function mountWorkplaceBundle(): void
    {
        $manifest = new \Quorum\Vite\Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $entry    = 'resources/vue/workplace-app/main.js';

        $this->bundleJs   = $manifest->assetUrl($entry);
        $this->bundleCss  = $manifest->cssUrls($entry);
        // `assetBaseUrl`: direct filesystem path for CSS/JS bundles (served by
        // the webserver, no Trails dispatcher).
        // `pluginUrl`: Trails dispatcher path for API + routes.
        $this->assetBaseUrl = $this->plugin->getPluginURL();
        $this->pluginUrl    = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->lang         = (string) ($GLOBALS['_language'] ?? 'de_DE');

        // Push CSS bundles into `<head>` — a `<link>` in `<body>` would load
        // Stud.IP default styles AFTER the Quorum tokens and override our Aurora
        // patterns. `PageLayout::addStylesheet` places the link so Quorum
        // specifics always win.
        $this->pushBundleCssToPageLayout();
    }

    /**
     * Hooks Quorum CSS bundles into the Stud.IP `<head>`. Idempotent — if the
     * same link is already present, PageLayout does not add it again.
     */
    private function pushBundleCssToPageLayout(): void
    {
        if (empty($this->bundleCss)) {
            return;
        }
        $base = rtrim((string) $this->assetBaseUrl, '/');
        foreach ($this->bundleCss as $css) {
            \PageLayout::addStylesheet($base . '/public/' . $css);
        }
    }

    /**
     * Form views (workplace/new, edit, restart, collection_form, …) need only
     * the token CSS, no Vue bundle. `pushFormCssToPageLayout` loads the
     * `workplace-app` CSS bundle into `<head>` without JS.
     */
    private function pushFormCssToPageLayout(): void
    {
        $manifest          = new \Quorum\Vite\Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $this->bundleCss   = $manifest->cssUrls('resources/vue/workplace-app/main.js');
        $this->assetBaseUrl = $this->plugin->getPluginURL();
        $this->pushBundleCssToPageLayout();
    }

    /**
     * Sidebar with a `ViewsWidget` for the active/collections/archive filter.
     * Stud.IP-native pattern — no Vue, no custom CSS, runs in the Stud.IP
     * PageLayout frame consistently with other sidebars.
     *
     * Three filters:
     *   - active      → standalone polls + current active view
     *   - collections → collections list (own Trails action)
     *   - archive     → archived polls
     */
    private function buildSidebar(string $activeView): void
    {
        $sidebar = \Sidebar::Get();
        // Stud.IP 6: `setTitle()` sets the header with the default icon.
        $sidebar->setTitle(_quorum('Quorum'));

        $views = new \ViewsWidget();
        $views->addLink(
            _quorum('Aktive Umfragen'),
            \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', true)
        )->setActive($activeView === 'active');
        $views->addLink(
            _quorum('Sammlungen'),
            \PluginEngine::getURL($this->plugin, [], 'workplace/collections', true)
        )->setActive($activeView === 'collections');
        $views->addLink(
            _quorum('Archiv'),
            \PluginEngine::getURL($this->plugin, ['view' => 'archive'], 'workplace/index', true)
        )->setActive($activeView === 'archive');
        $sidebar->addWidget($views);

        // Always offer both create actions (survey + collection) on every
        // workplace page for seamless switching.
        $actions = new \ActionsWidget();
        $actions->addLink(
            _quorum('Neue Umfrage anlegen'),
            \PluginEngine::getURL($this->plugin, [], 'workplace/new', true),
            \Icon::create('add')
        );
        $actions->addLink(
            _quorum('Neue Sammlung anlegen'),
            \PluginEngine::getURL($this->plugin, [], 'workplace/collection_new', true),
            \Icon::create('add')
        );
        $actions->addLink(
            _quorum('Umfrage importieren …'),
            \PluginEngine::getURL($this->plugin, [], 'workplace/import_file', true),
            \Icon::create('upload')
        );
        $actions->addLink(
            _quorum('Sammlung importieren …'),
            \PluginEngine::getURL($this->plugin, [], 'workplace/import_collection', true),
            \Icon::create('upload')
        );
        $sidebar->addWidget($actions);
    }

    /* ───────────────── Collections ───────────────── */

    /**
     * GET /workplace/collections — list of own collections.
     *
     * Mounts the `workplace-app` with `data-mode="collections"` — the
     * collection cards thus render as a Vue component with the same action
     * menu (QuorumActionMenu) and the same lifecycle flows as the
     * single-survey cards (live-test feedback: collections used to be plain
     * PHP cards without actions and therefore could not be started).
     */
    public function collections_action(): void
    {
        $this->ownerOrAccessDenied();
        $this->mountWorkplaceBundle();

        // Only active collections — the archive lives in the sidebar "archive"
        // view (workplace/index?view=archive), together with the archived
        // single surveys.
        $this->csrf = \CSRFProtection::token();
        // Pass the course context (cid) through to the Vue app so edit/detail
        // links stay inside the course frame (analogous to SurveysIndex →
        // workplace forms).
        $this->cid  = (string) ($this->courseId ?? '');

        \PageLayout::setTitle(_quorum('Sammlungen'));
        $this->buildSidebar('collections');
    }

    /**
     * GET /workplace/collection/{id} — collection detail page with member polls
     * in order + reorder buttons.
     */
    public function collection_action(string $collectionId = ''): void
    {
        $userId     = $this->ownerOrAccessDenied();
        $collection = $this->ownedCollectionOr404($collectionId, $userId);
        $service    = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        $this->collection = $collection;
        $this->polls      = $service->findPollsInCollection($collectionId);
        $this->csrf       = \CSRFProtection::tokenTag();
        $this->pluginUrl  = \PluginEngine::getURL($this->plugin, [], '', true);

        $plugin = $this->plugin;
        // In the course frame append cid to all navigation/form URLs so the
        // detail page + follow-up actions stay in the course context.
        $cid       = $this->courseId;
        $cidParams = $cid !== null ? ['cid' => $cid] : [];
        $this->reorderUrl   = \PluginEngine::getURL($plugin, $cidParams, 'workplace/collection_reorder/' . $collection->id, false);
        $this->editUrl      = \PluginEngine::getURL($plugin, $cidParams, 'workplace/collection_edit/' . $collection->id, false);
        $this->backUrl      = $cid !== null
            ? \PluginEngine::getURL($plugin, ['cid' => $cid], 'index/index', false)
            : \PluginEngine::getURL($plugin, [], 'workplace/collections', false);
        $this->presenterUrl = \PluginEngine::getURL($plugin, [], 'workplace/presenter/' . $collection->id, false);
        // Direct creation: a new poll is created right inside this collection
        // (create form with the collection target preset).
        $this->newPollUrl   = \PluginEngine::getURL($plugin, $cidParams + ['collection' => $collection->id], 'workplace/new', false);
        // Collection flow control: `anyActive` decides whether the Vue action
        // menu shows "Start voting" or "Finish voting". The lifecycle itself
        // runs over the JSON API (/api/collection_start|finish, CSRF) — the
        // former POST-form routes (workplace/collection_start|finish) were
        // dropped when the action menu replaced the button bar.
        $this->anyActive    = (bool) array_filter($this->polls, static fn (array $p) => !empty($p['is_active']));
        // Definition download (JSON) — direct asset via the API endpoint.
        $this->downloadUrl  = rtrim((string) $this->pluginUrl, '/') . '/api/download_collection/' . $collection->id;

        $manifest        = new \Quorum\Vite\Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $this->bundleCss = $manifest->cssUrls('resources/vue/workplace-app/main.js');

        // All management actions of the detail page in ONE action menu
        // (QuorumActionMenu like the cards), mounted as a slim Vue bundle,
        // because this page is rendered server-side. Lifecycle runs through
        // /api/collection_* (CSRF). "QR code / Share …" is a menu entry and
        // shows the collection's first question ("from the start").
        $firstPoll = $this->polls[0] ?? null;
        $this->actionsCsrf     = \CSRFProtection::token();
        $this->actionsHasPolls = !empty($this->polls);
        $this->qrJoinUrl  = $firstPoll ? $plugin->pollJoinUrl((string) $firstPoll['token']) : null;
        $this->qrShortUrl = $firstPoll
            ? $plugin->registerPollShortLink((string) $firstPoll['token'], $userId, (string) $firstPoll['question'])
            : null;
        $this->qrTitle    = $firstPoll['question'] ?? '';
        $this->actionsBundleJs  = $manifest->assetUrl('resources/vue/collection-actions-app/main.js');
        $this->actionsBundleCss = $manifest->cssUrls('resources/vue/collection-actions-app/main.js');

        \PageLayout::setTitle($collection->name);
        if ($cid !== null) {
            $this->buildCourseSidebar($cid, 'form');
        } else {
            $this->buildSidebar('collections');
        }
    }

    /** GET /workplace/collection_new — create form (full page). */
    public function collection_new_action(): void
    {
        $this->ownerOrAccessDenied();
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, 'form');
        } else {
            $this->buildSidebar('collections');
        }
        $this->renderCollectionForm(name: '', description: null, mode: 'create');
    }

    /** POST /workplace/collection_create — form handler. */
    public function collection_create_action(): void
    {
        if (\Request::method() !== 'POST') {
            throw new \AccessDeniedException(_quorum('POST erforderlich.'));
        }
        $userId = $this->ownerOrAccessDenied();
        \CSRFProtection::verifyUnsafeRequest();

        // Course assignment: validate the client `seminar_id` (hidden in the
        // course frame or QuickSearch in the workplace) against write
        // permission (IDOR protection).
        $seminarId = trim((string) \Request::get('seminar_id'));
        $seminarId = $seminarId === '' ? null : $seminarId;
        if (!$this->mayWriteSeminar($seminarId)) {
            throw new \AccessDeniedException(_quorum('Sie haben keine Berechtigung für diese Veranstaltung.'));
        }

        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        try {
            $collection = $service->createCollection(
                userId:      $userId,
                name:        (string) \Request::get('name'),
                description: ((string) \Request::get('description')) ?: null,
                seminarId:   $seminarId,
            );
            \PageLayout::postSuccess(_quorum('Sammlung angelegt.'));
            // Created from the course → back to the course tab; otherwise to
            // the workplace collection detail page.
            $this->redirect($this->courseId !== null
                ? \PluginEngine::getURL($this->plugin, ['cid' => $this->courseId], 'index/index', false)
                : \PluginEngine::getURL($this->plugin, [], 'workplace/collection/' . $collection->id, false));
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            \PageLayout::postError($e->getMessage());
            $newParams = $this->courseId !== null ? ['cid' => $this->courseId] : [];
            $this->redirect(\PluginEngine::getURL($this->plugin, $newParams, 'workplace/collection_new', false));
        }
    }

    /** GET /workplace/collection_edit/{id} — edit form. */
    public function collection_edit_action(string $collectionId = ''): void
    {
        $userId     = $this->ownerOrAccessDenied();
        $collection = $this->ownedCollectionOr404($collectionId, $userId);
        if ($this->courseId !== null) {
            $this->buildCourseSidebar($this->courseId, 'form');
        } else {
            $this->buildSidebar('collections');
        }
        $this->renderCollectionForm(
            name:        $collection->name,
            description: $collection->description,
            mode:        'edit',
            collection:  $collection,
        );
    }

    /** POST /workplace/collection_update/{id} — update handler. */
    public function collection_update_action(string $collectionId = ''): void
    {
        if (\Request::method() !== 'POST') {
            throw new \AccessDeniedException(_quorum('POST erforderlich.'));
        }
        $userId     = $this->ownerOrAccessDenied();
        $collection = $this->ownedCollectionOr404($collectionId, $userId);
        \CSRFProtection::verifyUnsafeRequest();

        // The course assignment can be changed while editing — check write
        // permission on the TARGET course (IDOR protection, as in create).
        $seminarId = trim((string) \Request::get('seminar_id'));
        $seminarId = $seminarId === '' ? null : $seminarId;
        if (!$this->mayWriteSeminar($seminarId)) {
            throw new \AccessDeniedException(_quorum('Sie haben keine Berechtigung für diese Veranstaltung.'));
        }

        $service = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        $cidParams = $this->courseId !== null ? ['cid' => $this->courseId] : [];
        try {
            $service->updateCollection(
                collectionId: $collection->id,
                name:         (string) \Request::get('name'),
                description:  ((string) \Request::get('description')) ?: null,
                seminarId:    $seminarId,
            );
            \PageLayout::postSuccess(_quorum('Sammlung aktualisiert.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, $cidParams, 'workplace/collection/' . $collection->id, false));
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            \PageLayout::postError($e->getMessage());
            $this->redirect(\PluginEngine::getURL($this->plugin, $cidParams, 'workplace/collection_edit/' . $collection->id, false));
        }
    }

    /**
     * POST /workplace/collection_reorder/{id} — new poll order.
     * Body: `polls[]` as an ordered list of poll IDs.
     */
    public function collection_reorder_action(string $collectionId = ''): void
    {
        if (\Request::method() !== 'POST') {
            throw new \AccessDeniedException(_quorum('POST erforderlich.'));
        }
        $userId     = $this->ownerOrAccessDenied();
        $collection = $this->ownedCollectionOr404($collectionId, $userId);
        \CSRFProtection::verifyUnsafeRequest();

        $orderedIds = array_values(array_map('strval', (array) \Request::getArray('polls')));
        $service    = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        $service->reorderPolls($collection->id, $orderedIds);
        \PageLayout::postSuccess(_quorum('Reihenfolge gespeichert.'));
        $this->redirect($this->collectionDetailUrl($collection->id));
    }

    /**
     * GET /workplace/collection_assign/{pollId} — form with collection choice,
     * opened from the action menu on a poll card.
     */
    public function collection_assign_action(string $pollId = ''): void
    {
        $userId      = $this->ownerOrAccessDenied();
        $pollsRepo   = new \Quorum\Polls\PollsRepository();
        $poll        = $pollsRepo->findById($pollId);
        if ($poll === null || $poll->userId !== $userId) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        $service          = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            $pollsRepo,
        );
        $this->poll       = $poll;
        $this->collections = $service->findSummariesByUser($userId, 'active');
        $this->csrf       = \CSRFProtection::tokenTag();
        $this->actionUrl  = \PluginEngine::getURL($this->plugin, [], 'workplace/collection_assign_submit/' . $poll->id, false);
        $this->cancelUrl  = \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false);
        $this->pluginUrl  = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->buildSidebar('active');
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle(_quorum('Zu Sammlung hinzufügen'));
        $this->render_template('workplace/collection_assign', $this->layout);
    }

    /** POST /workplace/collection_assign_submit/{pollId} */
    public function collection_assign_submit_action(string $pollId = ''): void
    {
        if (\Request::method() !== 'POST') {
            throw new \AccessDeniedException(_quorum('POST erforderlich.'));
        }
        $userId    = $this->ownerOrAccessDenied();
        $pollsRepo = new \Quorum\Polls\PollsRepository();
        $poll      = $pollsRepo->findById($pollId);
        if ($poll === null || $poll->userId !== $userId) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        \CSRFProtection::verifyUnsafeRequest();

        $service      = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            $pollsRepo,
        );
        $collectionId = trim((string) \Request::get('collection_id'));

        try {
            if ($collectionId === '') {
                // Empty choice = remove from collection
                $service->removePollFromCollection($poll->id);
                \PageLayout::postSuccess(_quorum('Umfrage aus Sammlung entfernt.'));
            } else {
                // Owner check of the target collection
                $this->ownedCollectionOr404($collectionId, $userId);
                $service->addPollToCollection($collectionId, $poll->id, $userId);
                \PageLayout::postSuccess(_quorum('Umfrage zur Sammlung hinzugefügt.'));
            }
            $this->redirect(\PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', false));
        } catch (\Quorum\Polls\Exceptions\PollNotFoundException $e) {
            \PageLayout::postError($e->getMessage());
            $this->redirect(\PluginEngine::getURL($this->plugin, [], 'workplace/collection_assign/' . $poll->id, false));
        }
    }

    /* ───────────────── Compare view ───────────────── */

    /**
     * GET /workplace/compare/{rootId} — peer-instruction compare full page.
     *
     * Mounts the `workplace-app` with `data-mode="compare"` + `data-root-id` so
     * the Vue bootstrapper renders the compare component instead of the index
     * list. Owner auth is enforced both here (only lecturers with min role open
     * the page) and in the API endpoint (strict owner identity).
     *
     * Information-leakage protection: 404 for foreign root polls.
     */
    public function compare_action(string $rootId = ''): void
    {
        $userId = $this->ownerOrAccessDenied();
        $repo   = new \Quorum\Polls\PollsRepository();
        $root   = $repo->findById($rootId);
        if ($root === null || !$this->mayAccessPoll($root, $userId)) {
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        $this->mountWorkplaceBundle();
        $this->mode    = 'compare';
        $this->rootId  = $root->id;
        $this->rootQ   = $root->question;
        $this->csrf    = \CSRFProtection::token();
        $this->backUrl = \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', true);

        \PageLayout::setTitle(_quorum('Abstimmungs-Vergleich'));
        $this->buildSidebar('active');
    }

    /* ───────────────── Presenter mode ───────────────── */

    /**
     * GET /workplace/presenter/{collectionId} — presenter fullscreen for a
     * collection. Loads the polls in `collection_position` order and mounts the
     * Vue presenter app, which handles the fullscreen API + keyboard shortcuts
     * + live charts (via the SSE composable + ResultsContainer) itself.
     */
    public function presenter_action(string $collectionId = ''): void
    {
        $userId     = $this->ownerOrAccessDenied();
        $collection = $this->ownedCollectionOr404($collectionId, $userId);
        $service    = new \Quorum\Polls\CollectionsService(
            new \Quorum\Polls\CollectionsRepository(),
            new \Quorum\Polls\PollsRepository(),
        );
        $polls      = $service->findPollsInCollection($collection->id);
        // Enrich each poll with the anonymous join URL (QR target) so the
        // presenter can show a scannable QR code per question.
        $plugin = $this->plugin;
        $polls  = array_map(
            static function (array $p) use ($plugin): array {
                $p['join_url'] = $plugin->pollJoinUrl((string) $p['token']);
                return $p;
            },
            $polls,
        );

        $manifest        = new \Quorum\Vite\Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $entry           = 'resources/vue/presenter-app/main.js';
        $this->bundleJs  = $manifest->assetUrl($entry);
        $this->bundleCss = $manifest->cssUrls($entry);
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->lang      = (string) ($GLOBALS['_language'] ?? 'de_DE');
        $this->csrf      = \CSRFProtection::token();
        $this->collection = [
            'id'   => $collection->id,
            'name' => $collection->name,
        ];
        $this->polls     = $polls;
        $this->returnUrl = \PluginEngine::getURL($this->plugin, [], 'workplace/collection/' . $collection->id, true);

        \PageLayout::setTitle($collection->name . ' — ' . _quorum('Presenter-Modus'));
        $this->set_layout(null);   // Fullscreen mount, no Stud.IP sidebar/header
        $this->render_template('workplace/presenter');
    }

    /**
     * GET /workplace/present_poll/{pollId} — presenter fullscreen for a SINGLE
     * survey, without it having to be in a collection.
     *
     * A collection is just an ordered grouping of surveys; a single survey is
     * the trivial one-element case. The presenter-app derives everything
     * (current question, total, next/previous) from the `polls` list — so a
     * one-element list and a pseudo-collection (title = question) suffice. No
     * frontend special case needed.
     *
     * Auth: poll owner (same as the collection presenter).
     */
    public function present_poll_action(string $pollId = ''): void
    {
        $userId    = $this->ownerOrAccessDenied();
        $pollsRepo = new \Quorum\Polls\PollsRepository();
        $poll      = $pollsRepo->findById($pollId);
        if ($poll === null || !$this->mayAccessPoll($poll, $userId)) {
            // 404-equivalent: don't reveal foreign/missing IDs as existing.
            throw new \AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }

        // Same array shape as collection polls (see `findPollsInCollection`).
        $pollRow = [
            'id'             => $poll->id,
            'token'          => $poll->token,
            'question'       => $poll->question,
            'type'           => $poll->type,
            'options'        => $poll->options,
            'is_active'      => $poll->isActive,
            'position'       => 0,
            'response_count' => $pollsRepo->countResponses($poll->id),
            'join_url'       => $this->plugin->pollJoinUrl($poll->token),
        ];

        $manifest        = new \Quorum\Vite\Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $entry           = 'resources/vue/presenter-app/main.js';
        $this->bundleJs  = $manifest->assetUrl($entry);
        $this->bundleCss = $manifest->cssUrls($entry);
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->lang      = (string) ($GLOBALS['_language'] ?? 'de_DE');
        $this->csrf      = \CSRFProtection::token();
        $this->collection = [
            'id'   => '',
            'name' => $poll->question,
        ];
        $this->polls     = [$pollRow];
        $this->returnUrl = \PluginEngine::getURL($this->plugin, ['view' => 'active'], 'workplace/index', true);

        \PageLayout::setTitle($poll->question . ' — ' . _quorum('Presenter-Modus'));
        $this->set_layout(null);   // Fullscreen mount, no Stud.IP sidebar/header
        $this->render_template('workplace/presenter');
    }

    /* ───────────────── private helpers ───────────────── */

    private function ownerOrAccessDenied(): string
    {
        $perm    = $GLOBALS['perm']  ?? null;
        $userId  = (string) ($GLOBALS['user']->id ?? '');
        $minRole = \QuorumStudipPlugin::minRole();
        if (!$perm || !$perm->have_perm($minRole) || $userId === '' || $userId === 'nobody') {
            throw new \AccessDeniedException(_quorum('Anmeldung erforderlich.'));
        }
        return $userId;
    }

    private function ownedCollectionOr404(string $collectionId, string $userId): \Quorum\Polls\Collection
    {
        if ($collectionId === '') {
            throw new \AccessDeniedException(_quorum('Sammlung nicht gefunden.'));
        }
        $repo       = new \Quorum\Polls\CollectionsRepository();
        $collection = $repo->findById($collectionId);
        // Co-teaching: owner OR tutor in the collection's assigned course may
        // manage it. Course-independent collections (seminarId === null) stay
        // owner-only. `seminarId` comes from the DB, never from the request
        // (fail-closed). Same message as "not found" (information-leakage
        // protection).
        $mayTutor = $collection !== null
            && $collection->seminarId !== null
            && $this->mayWriteSeminar($collection->seminarId);
        if ($collection === null || ($collection->userId !== $userId && !$mayTutor)) {
            throw new \AccessDeniedException(_quorum('Sammlung nicht gefunden.'));
        }
        return $collection;
    }

    /**
     * Renders the collection create/edit form (shared view).
     */
    private function renderCollectionForm(
        string $name,
        ?string $description,
        string $mode,
        ?\Quorum\Polls\Collection $collection = null,
    ): void {
        $this->name        = $name;
        $this->description = (string) ($description ?? '');
        $this->mode        = $mode;
        $this->collection  = $collection;
        $this->csrf        = \CSRFProtection::tokenTag();
        // Course frame: cid as a query on the form URLs so create/edit stays
        // in the course context (analogous to the poll form).
        $cid           = $this->courseId;
        $cidParams     = $cid !== null ? ['cid' => $cid] : [];
        $this->cid     = (string) ($cid ?? '');
        // Course binding: locked in the course frame (hidden + info), otherwise
        // free choice. When editing, the form shows the collection's existing
        // assignment.
        $this->seminarId     = $cid ?? ($collection->seminarId ?? '');
        $this->seminarLocked = $cid !== null;
        $this->seminarName   = $this->seminarDisplayName($this->seminarId);
        $this->actionUrl   = $mode === 'edit'
            ? \PluginEngine::getURL($this->plugin, $cidParams, 'workplace/collection_update/' . $collection->id, false)
            : \PluginEngine::getURL($this->plugin, $cidParams, 'workplace/collection_create', false);
        $this->cancelUrl   = $mode === 'edit'
            ? \PluginEngine::getURL($this->plugin, $cidParams, 'workplace/collection/' . $collection->id, false)
            : ($cid !== null
                ? \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/index', false)
                : \PluginEngine::getURL($this->plugin, [], 'workplace/collections', false));
        $this->pluginUrl   = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->pushFormCssToPageLayout();

        \PageLayout::setTitle($mode === 'edit' ? _quorum('Sammlung bearbeiten') : _quorum('Neue Sammlung anlegen'));
        $this->render_template('workplace/collection_form', $this->layout);
    }

    /** Collection detail page, preserving cid in the course frame. */
    private function collectionDetailUrl(string $collectionId): string
    {
        $params = $this->courseId !== null ? ['cid' => $this->courseId] : [];
        return \PluginEngine::getURL($this->plugin, $params, 'workplace/collection/' . $collectionId, false);
    }

    /** Display name of a course (empty if none/unknown). */
    private function seminarDisplayName(?string $seminarId): string
    {
        if ($seminarId === null || $seminarId === '') {
            return '';
        }
        try {
            $course = \Course::find($seminarId);
            return $course ? (string) $course->name : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
