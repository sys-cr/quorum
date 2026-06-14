<?php

declare(strict_types=1);

use Quorum\Vite\Manifest;

/**
 * Anonymous student voting page (Vue polls-app).
 *
 * Route: GET /plugins.php/quorumstudipplugin/p/show/{token}
 *
 * This is the target of the QR code / short link: students reach the page
 * without a Stud.IP login. Like `UController`, the controller needs the nobody
 * role (`$allow_nobody = true`), otherwise the authentication middleware blocks
 * it first.
 *
 * Standalone mobile page (layout `null`, no Stud.IP frame) — the polls-app
 * renders the whole surface and fetches its data via `GET /api/polls/{token}`.
 * The token is passed to the Vue mount point (`#quorum-polls-app`) via
 * `data-token`.
 *
 * The action is `show_action` (not `index_action`) so Trails does not mistake
 * the token for an action name (same convention as `UController::r_action`).
 */
class PController extends PluginController
{
    use \Quorum\Controllers\ManualDownloadTrait;

    protected $allow_nobody = true;

    public function show_action(string $token = ''): void
    {
        if ($token === '') {
            throw new Trails_Exception(404, 'Abstimmungs-Token fehlt.');
        }

        $manifest = new Manifest(__DIR__ . '/../public/.vite/manifest.json');
        $entry    = 'resources/vue/polls-app/main.js';

        $this->bundleJs  = $manifest->assetUrl($entry);
        $this->bundleCss = $manifest->cssUrls($entry);
        // Direct filesystem path for the static bundles (served by the webserver
        // without the Trails dispatcher) — same pattern as presenter.php.
        $this->assetBaseUrl = $this->plugin->getPluginURL();
        $this->pluginUrl    = \PluginEngine::getURL($this->plugin, [], '', true);
        $this->token        = $token;
        $this->lang         = (string) ($GLOBALS['_language'] ?? 'de_DE');
        // Student manual (only the "Teilnehmen" section) as PDF — linked in the
        // footer of the vote page.
        $this->manualUrl    = \PluginEngine::getURL($this->plugin, [], 'p/manual', true);

        $this->set_layout(null);   // Full-surface mobile page, no Stud.IP frame
        $this->render_template('p/show');
    }

    /**
     * GET /p/manual — downloads the participant manual (only "Teilnehmen") as a
     * Stud.IP PDF. Anonymous (`$allow_nobody`); session language.
     */
    public function manual_action(): void
    {
        $this->sendManualPdf(\Quorum\Manual\ManualService::AUDIENCE_STUDENT);
    }
}
