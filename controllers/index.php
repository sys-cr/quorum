<?php

declare(strict_types=1);

use Quorum\Vite\Manifest;

/**
 * Lecturer main page of the course-app.
 *
 * Route: GET /plugins.php/quorumstudipplugin/index/index?cid={cid}
 *
 * Mounts the Vue 3 course-app. Permission is checked in before_filter
 * (tutor level for the course); the view renders only the mount point
 * plus the Vite-hashed asset URLs, the rest is rendered by Vue.
 *
 * @property string             $bundleJs   path within public/assets/
 * @property array<int,string>  $bundleCss  CSS paths within public/assets/
 * @property string             $cid        Stud.IP course ID
 * @property string             $pluginUrl  plugin public URL root
 * @property string             $lang       Stud.IP locale (e.g. 'de_DE')
 */
class IndexController extends PluginController
{
    use \Quorum\Controllers\CourseFrameTrait;
    use \Quorum\Controllers\UploadDefinitionTrait;

    private Manifest $vite;

    public function before_filter(&$action, &$args): void
    {
        parent::before_filter($action, $args);

        // Permission: course membership is enough for the overview (`index`) —
        // students see running polls + released results there (read-only). The
        // management actions (create/import) remain reserved for tutor level.
        // Anonymous participation continues via the polls-app by token (no
        // login).
        $cid = Request::option('cid');
        if ($cid === null || !$GLOBALS['perm']->have_studip_perm('user', $cid)) {
            throw new AccessDeniedException(_quorum('Sie sind nicht Mitglied dieser Veranstaltung.'));
        }
        $this->isTutor = $GLOBALS['perm']->have_studip_perm('tutor', $cid);
        if ($action !== 'index' && !$this->isTutor) {
            throw new AccessDeniedException(_quorum('Sie benötigen Tutor-Rechte in dieser Veranstaltung.'));
        }

        // Force the course context frame (header/tabs/sidebar) via CourseFrameTrait.
        $this->activateCourseFrame();

        // Context help (per view) + GPLv3 §7 attribution in the helpbar.
        // Students get a dedicated, participation-oriented text.
        $helpKey = (!$this->isTutor && $action === 'index') ? 'student' : $action;
        \Quorum\AttributionHelper::addToHelpbar('course/' . $helpKey);

        $this->vite = new Manifest(__DIR__ . '/../public/.vite/manifest.json');
    }

    /**
     * GET /index/index — renders the mount point and loads the course-app bundle.
     */
    public function index_action(): void
    {
        $entry = 'resources/vue/course-app/main.js';
        $this->bundleJs  = $this->vite->assetUrl($entry);
        $this->bundleCss = $this->vite->cssUrls($entry);
        $this->cid       = Request::option('cid', '');
        // `assetBaseUrl` = filesystem path for static assets (CSS/JS bundles),
        // served directly by the webserver via `getPluginURL()`.
        // `pluginUrl` = Trails dispatcher path for API calls + routes via
        // `PluginEngine::getURL()`. The two differ: static assets resolve only
        // under the filesystem path, the dispatcher path would 404 for them.
        $this->assetBaseUrl = $this->plugin->getPluginURL();
        $this->pluginUrl    = \PluginEngine::getURL($this->plugin, [], '', true);

        // Active UI language = `$_SESSION['_language']` (Stud.IP sets it in
        // language.inc.php; the sidebar gettext follows the same source). In the
        // plugin controller context `$GLOBALS['_language']` is often NOT set →
        // fallback only. Passed to STUDIP.LANGUAGE_BASE / data-lang.
        $this->lang = (string) ($_SESSION['_language'] ?? $GLOBALS['_language'] ?? 'de_DE');

        // The role controls which component the course-app mounts (teachers:
        // management; students: read-only participation/result view).
        $this->role = $this->isTutor ? 'tutor' : 'student';

        // Management sidebar (active/archive/new/import) only for teachers —
        // students see no management navigation.
        if ($this->isTutor) {
            $this->buildCourseSidebar($this->cid, (string) \Request::option('view', 'active'));
        }
    }

    /**
     * GET /index/import?cid={cid} — "embed survey": lists the user's own
     * surveys NOT in this course and offers an adopt action per survey (binds
     * `seminar_id` to the course). Renders in the course frame.
     */
    public function import_action(): void
    {
        $cid    = Request::option('cid', '');
        $userId = (string) ($GLOBALS['user']->id ?? '');

        $repo = new \Quorum\Polls\PollsRepository();
        // Own root surveys not yet in this course (course-independent OR in
        // a different course).
        $this->importable = array_values(array_filter(
            $repo->findSummariesByUser($userId, 'all'),
            static fn(\Quorum\Polls\PollSummary $p): bool => $p->seminarId !== $cid,
        ));
        $this->cid       = $cid;
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->pluginUrl = \PluginEngine::getURL($this->plugin, [], '', true);

        // Keep the course sidebar for seamless switching between views.
        $this->buildCourseSidebar($cid, 'form');
        \PageLayout::setTitle(_quorum('Umfrage einbinden'));
        $this->render_template('index/import', $this->layout);
    }

    /**
     * POST /index/import_submit/{pollId}?cid={cid} — binds the chosen (own)
     * survey to the current course.
     */
    public function import_submit_action(string $pollId = ''): void
    {
        \CSRFProtection::verifyUnsafeRequest();

        $cid    = Request::option('cid', '');
        $userId = (string) ($GLOBALS['user']->id ?? '');

        $repo = new \Quorum\Polls\PollsRepository();
        $poll = $repo->findById($pollId);
        if ($poll === null || $poll->userId !== $userId) {
            throw new AccessDeniedException(_quorum('Diese Abstimmung existiert nicht.'));
        }
        $repo->setSeminar($poll->id, $cid !== '' ? $cid : null);

        \PageLayout::postSuccess(_quorum('Umfrage wurde in diesen Kurs übernommen.'));
        $this->redirect(\PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/index', false));
    }

    /**
     * GET /index/import_file?cid={cid} — "import survey": upload form for a
     * previously downloaded Quorum definition (`.json`). The imported survey is
     * created fresh in this course (0 responses).
     */
    public function import_file_action(): void
    {
        $cid = Request::option('cid', '');

        $this->cid       = $cid;
        $this->csrf      = \CSRFProtection::tokenTag();
        $this->actionUrl = \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/import_file_submit', false);

        // Keep the course sidebar for seamless switching.
        $this->buildCourseSidebar($cid, 'form');
        \PageLayout::setTitle(_quorum('Umfrage importieren'));
        $this->render_template('index/import_file', $this->layout);
    }

    /**
     * POST /index/import_file_submit?cid={cid} — takes the uploaded definition
     * file, validates it and creates a new survey in the course. Errors are
     * reported via the Stud.IP notification bar.
     */
    public function import_file_submit_action(): void
    {
        \CSRFProtection::verifyUnsafeRequest();

        $cid    = Request::option('cid', '');
        $userId = (string) ($GLOBALS['user']->id ?? '');
        $back   = \PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/import_file', false);

        try {
            // cid is covered by before_filter (tutor in course) here — no extra
            // seminar check needed (unlike workplace/new).
            $def     = \Quorum\Polls\SurveyDefinition::fromJson($this->readUploadedDefinition('definition'));
            $service = new \Quorum\Polls\PollsService(new \Quorum\Polls\PollsRepository());
            $poll    = $service->createPoll(
                userId:    $userId,
                question:  $def['question'],
                type:      $def['type'],
                options:   $def['options'],
                seminarId: $cid !== '' ? $cid : null,
            );
            $this->plugin->registerPollShortLink($poll->token, $userId, $poll->question);
            \PageLayout::postSuccess(_quorum('Umfrage importiert.'));
            $this->redirect(\PluginEngine::getURL($this->plugin, ['cid' => $cid], 'index/index', false));
        } catch (\Quorum\Polls\Exceptions\InvalidResponseException $e) {
            \PageLayout::postError($e->getMessage());
            $this->redirect($back);
        } catch (\Throwable $e) {
            // Don't let broken JSON surface as a 500/stack trace.
            \PageLayout::postError(_quorum('Die Datei konnte nicht importiert werden (ungültiges Format).'));
            $this->redirect($back);
        }
    }

}
