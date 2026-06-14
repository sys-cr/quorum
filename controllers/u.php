<?php

declare(strict_types=1);

use Quorum\Url\AdapterDetector;
use Quorum\Url\ShortUrlService;

/**
 * Plugin-owned anonymous short-link resolver.
 *
 * Stud.IP's own `UController` (`/dispatch.php/u/r/{alias}`) extends
 * `AuthenticatedController` and thus blocks students without login. The
 * polls-app needs an anonymous resolver.
 *
 * Route: GET /plugins.php/quorumstudipplugin/u/r/{alias}
 *
 * The action is `r_action` (not `index_action`) — Stud.IP's URL-resolver
 * convention (`UController::r_action` under `dispatch.php/u/r/{alias}`).
 * Otherwise Trails would interpret the alias of a direct `/u/{alias}` as the
 * action name and throw a routing error.
 *
 * Requires the plugin to have the nobody role (id=7) assigned, otherwise the
 * Stud.IP authentication middleware kicks in before this controller. This is
 * done automatically by the `QuorumStudipPlugin::onEnable()` hook.
 */
class UController extends PluginController
{
    /**
     * Allow anonymous requests (default `true` in `StudipController`, explicit
     * here for documentation). Effective only once the plugin has assigned the
     * nobody role.
     */
    protected $allow_nobody = true;

    public function r_action(string $alias = ''): void
    {
        if ($alias === '') {
            throw new Trails_Exception(404, 'Kurzlink-Alias fehlt.');
        }

        $service = new ShortUrlService(AdapterDetector::fromDatabase()->detect());
        $data    = $service->resolve($alias);

        if ($data === null) {
            throw new Trails_Exception(404, 'Kurzlink nicht gefunden.');
        }

        // URLHelper builds the absolute URL including the base path.
        $this->redirect(URLHelper::getURL($data->path, ['from_short_url' => $data->id]));
    }
}
