<?php

declare(strict_types=1);

/**
 * Quorum — Stud.IP 6 plugin bootstrap.
 *
 * Stud.IP convention: this file sits in the plugin root, the class name matches
 * `pluginclassname` from `plugin.manifest`. It lives in the global namespace
 * (no PSR-4 for the bootstrap class).
 *
 * Adapter that gives Stud.IP enough to register the plugin via
 * `studip plugin:register`. The domain logic lives entirely in the PSR-4
 * namespace `Quorum\` (see `lib/`).
 *
 * @author    Bodo Steffen
 * @copyright 2026 Bodo Steffen
 * @license   GPL-3.0-or-later WITH additional terms (see SUPPLEMENTAL-TERMS.txt)
 * @link      https://github.com/sys-cr/quorum
 */
class QuorumStudipPlugin extends StudIPPlugin implements StandardPlugin, SystemPlugin, PrivacyPlugin
{
    public function __construct()
    {
        parent::__construct();

        // Translation helper for the plugin's own UI strings (`_quorum()`).
        // Required eagerly because the SPL autoloader below only loads on the
        // first `Quorum\…` class lookup — too late for a plain function used
        // directly in controllers and views.
        require_once __DIR__ . '/lib/i18n.php';

        // Lazy Composer autoload via SPL: initialize the Composer autoloader
        // only when a `Quorum\…` class is actually referenced. Stud.IP calls
        // the plugin constructor on EVERY page request, but many pages use no
        // Quorum classes. A direct `require_once vendor/autoload.php` would
        // still initialize Composer's class map (5–15 ms) for nothing. The SPL
        // wrapper runs the init only on demand.
        spl_autoload_register(self::loadQuorumClass(...));

        // Hook the tile into "My workplace" (`dispatch.php/contents/overview`),
        // a Navigation-based tile list via `Navigation::getItem('/contents')`.
        // `registerWorkplaceTile` early-returns via `Navigation::hasItem` on
        // pages without contents navigation (<1 ms).
        $this->registerWorkplaceTile();
    }

    /**
     * SPL autoloader for the `Quorum\…` namespace. Loads Composer's actual
     * autoloader on the first `Quorum\…` class lookup per request and then
     * delegates to Composer's PSR-4 resolver.
     *
     * During an SPL-callback iteration PHP 8.2 does not consider newly
     * registered autoloaders for the current class. Hence we call
     * `$loader->loadClass()` explicitly after the `require_once` instead of
     * waiting for PHP to invoke Composer's ClassLoader again.
     */
    private static function loadQuorumClass(string $class): void
    {
        if (!str_starts_with($class, 'Quorum\\')) {
            return;
        }
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return;
        }
        // require_once returns the ClassLoader on the first include, then true.
        $loader = require_once $autoload;
        if ($loader instanceof \Composer\Autoload\ClassLoader) {
            $loader->loadClass($class);
        }
    }

    /**
     * Registers the Quorum tile under "My workplace". Visible only to users at
     * or above the minimum role (`QUORUM_MIN_ROLE`, default `dozent`).
     */
    private function registerWorkplaceTile(): void
    {
        if (!\Navigation::hasItem('/contents')) {
            return;
        }
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_perm(self::minRole())) {
            return;
        }
        $tile = new \Navigation(
            _('Quorum'),
            \PluginEngine::getURL($this, [], 'workplace/index', true)
        );
        $tile->setImage(\Icon::create('vote'));
        $tile->setDescription(_('Live-Abstimmungen anlegen und auswerten'));
        \Navigation::addItem('/contents/quorum', $tile);
    }

    /**
     * Default minimum role for the workplace tile + admin full page.
     * Stud.IP hierarchy: nobody < user < autor < tutor < dozent < admin < root.
     * Admins can change the value via the Stud.IP system configuration
     * (`QUORUM_MIN_ROLE`).
     */
    public const CONFIG_MIN_ROLE         = 'QUORUM_MIN_ROLE';
    public const DEFAULT_MIN_ROLE        = 'dozent';
    private const ALLOWED_ROLES          = ['user', 'autor', 'tutor', 'dozent', 'admin', 'root'];

    /** Comma-separated free-text blocklist (empty = moderation off). */
    public const CONFIG_FREITEXT_BLOCKLIST = 'QUORUM_FREITEXT_BLOCKLIST';

    /**
     * Reads the configured minimum role and falls back to `DEFAULT_MIN_ROLE`
     * for unknown/empty values. The whitelist prevents accidentally setting
     * `nobody`, which would open the widget to everyone (incl. anonymous
     * students).
     */
    public static function minRole(): string
    {
        $val = (string) (\Config::get()->{self::CONFIG_MIN_ROLE} ?? '');
        return in_array($val, self::ALLOWED_ROLES, true) ? $val : self::DEFAULT_MIN_ROLE;
    }

    /**
     * Plugin lifecycle hook: called when the plugin is enabled.
     *
     *   1. Assigns the nobody role (id=7) so the anonymous polls resolver
     *      (`UController::r_action`) and the polls-app are reachable without
     *      students logging into Stud.IP.
     *   2. Creates the config value `QUORUM_MIN_ROLE` if not present. Default
     *      `dozent`, admin-configurable via the Stud.IP system configuration.
     */
    public static function onEnable($pluginId): void
    {
        \RolePersistence::assignPluginRoles($pluginId, [7]);

        self::ensureMinRoleConfig();
        // Remove any legacy PortalPlugin-widget registration of the Quorum tile
        // from widget_default and widget_user — the tile now lives under
        // "My workplace" via a Navigation item.
        self::removeLegacyWidgetEntries($pluginId);
    }

    /**
     * Removes traces of the legacy PortalPlugin tile from the Stud.IP widget
     * tables. Idempotent.
     */
    public static function removeLegacyWidgetEntries(int|string $pluginId): void
    {
        \DBManager::get()
            ->prepare('DELETE FROM widget_default WHERE pluginid = ?')
            ->execute([(int) $pluginId]);
        \DBManager::get()
            ->prepare('DELETE FROM widget_user WHERE pluginid = ?')
            ->execute([(int) $pluginId]);
    }

    /**
     * Creates the `QUORUM_MIN_ROLE` config entry in the Stud.IP system
     * configuration if it does not yet exist. Idempotent — called both from
     * `onEnable()` (first activation) and from the migration (existing installs).
     *
     * `section: 'quorum'` makes the value appear under its own "Quorum" section
     * in the Stud.IP system configuration, not as an anonymous entry in the
     * global section.
     */
    public static function ensureMinRoleConfig(): void
    {
        $config = \Config::get();
        if ($config->{self::CONFIG_MIN_ROLE} === null) {
            $config->create(self::CONFIG_MIN_ROLE, [
                'value'       => self::DEFAULT_MIN_ROLE,
                'type'        => 'string',
                'range'       => 'global',
                'section'     => 'quorum',
                'description' => 'Quorum: ab welcher Stud.IP-Systemrolle die Arbeitsplatz-Kachel '
                               . 'sichtbar wird (user, autor, tutor, dozent, admin, root). Default: dozent.',
            ]);
        }
        // Free-text blocklist (comma-separated). Empty = moderation off.
        if ($config->{self::CONFIG_FREITEXT_BLOCKLIST} === null) {
            $config->create(self::CONFIG_FREITEXT_BLOCKLIST, [
                'value'       => '',
                'type'        => 'string',
                'range'       => 'global',
                'section'     => 'quorum',
                'description' => 'Quorum: komma-getrennte Sperrbegriffe für anonyme '
                               . 'Freitext-/Word-Cloud-Beiträge. Treffer werden serverseitig '
                               . 'abgelehnt (Wort-Grenzen, case-insensitiv). Leer = Moderation aus.',
            ]);
        }
    }

    /**
     * On disable, clean up only legacy widget traces — the `QUORUM_*` config
     * keys are deliberately kept. Deleting them here would reset
     * admin-maintained values (free-text blocklist, min_role) to
     * default on every brief disable (e.g. for an update). The keys are
     * `QUORUM_`-prefixed and thus collision-free; cleanup belongs in a real
     * uninstall path, not the disable hook.
     */
    public static function onDisable($pluginId): void
    {
        self::removeLegacyWidgetEntries($pluginId);
    }

    /**
     * GDPR Art. 15 (subject access): exports a person's personal Quorum data
     * into Stud.IP's data-disclosure mechanism. Only the records created by the
     * owner (lecturer) carry personal references: polls, collections and short
     * URLs. The submitted answers (`quorum_responses`) are anonymous (no
     * `user_id`) and thus not part of this person's disclosure — they do not
     * belong to them but are other people's anonymous votes.
     */
    public function exportUserData(StoredUserData $storage): void
    {
        $userId = $storage->user_id;
        if ($userId === null || $userId === '') {
            return;
        }
        $db = \DBManager::get();

        $tables = [
            'quorum_polls'            => _quorum('Quorum-Umfragen'),
            'quorum_poll_collections' => _quorum('Quorum-Sammlungen'),
            'quorum_short_urls'       => _quorum('Quorum-Kurz-URLs'),
        ];
        foreach ($tables as $table => $label) {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE user_id = ? ORDER BY mkdate");
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows) {
                $storage->addTabularData($label, $table, $rows);
            }
        }
    }

    /* ------------------------------------------------------------------
       StandardPlugin implementation: course-tab integration for the
       lecturer `course-app`.
       ------------------------------------------------------------------ */

    /**
     * Returns the course-tab navigation. Stud.IP renders the plugin tab in the
     * course header from it. A click opens the `course-app` (Vue 3 mount, see
     * `IndexController::index_action`).
     *
     * @return array<string,\Navigation>
     */
    public function getTabNavigation($courseId): array
    {
        // Tab for all course members: teachers (tutor level) get the management
        // view, students a read-only participation/result view (`IndexController`
        // branches by role). Non-members see no tab.
        $perm = $GLOBALS['perm'] ?? null;
        if (!$perm || !$perm->have_studip_perm('user', $courseId)) {
            return [];
        }
        $url = \PluginEngine::getURL($this, ['cid' => $courseId], 'index/index', true);
        $tab = new \Navigation('Quorum', $url);
        $tab->setImage(\Icon::create('vote', \Icon::ROLE_INACTIVE));
        $tab->setActiveImage(\Icon::create('vote', \Icon::ROLE_NAVIGATION));
        return ['quorum' => $tab];
    }

    /**
     * Sidebar icon navigation in the course overview. Currently empty (the tab
     * is enough). Required by the Stud.IP StandardPlugin interface.
     */
    public function getIconNavigation($courseId, $lastVisit, $userId = null)
    {
        return null;
    }

    /**
     * Notification objects for the Stud.IP activity stream. Currently no push
     * notifications from the plugin.
     */
    public function getNotificationObjects($courseId, $since, $userId)
    {
        return [];
    }

    /**
     * Info template for the course detail page. Currently no own snippet —
     * Stud.IP default behavior is sufficient.
     */
    public function getInfoTemplate($courseId)
    {
        return null;
    }

    /**
     * Absolute URL of the anonymous voting page (QR-code/short-link target) for
     * a poll token. Single source for the route `p/show/{token}`
     * (`PController::show_action`) — controllers pass the result as `join_url`
     * to the lecturer frontends, which render the QR code from it.
     */
    public function pollJoinUrl(string $token): string
    {
        return \PluginEngine::getURL($this, [], 'p/show/' . rawurlencode($token), true);
    }

    /**
     * Registers (idempotently) a short link to the anonymous voting page of a
     * poll token and returns the absolute resolver URL (`u/r/{alias}`, anonymously
     * resolvable) or `null`.
     *
     * Via the native adapter the short link appears automatically in "My
     * workplace → My short links" (Stud.IP ≥ 6.2); on 6.0/6.1 the
     * `AdapterDetector` uses the own `quorum_short_urls` table.
     *
     * Best-effort: short links are convenience — if creation fails (e.g. the
     * `short_urls` table is missing in a partial setup), neither creating nor
     * the detail view of a poll may break. Idempotent, since
     * `ShortUrlService::create()` returns the existing alias for the same
     * path+user.
     */
    public function registerPollShortLink(string $token, string $userId, string $title = ''): ?string
    {
        try {
            $service = new \Quorum\Url\ShortUrlService(
                \Quorum\Url\AdapterDetector::fromDatabase()->detect()
            );
            $alias = $service->create(\Quorum\Url\PollLink::sharePath($token), $userId, $title);
            return \PluginEngine::getURL($this, [], 'u/r/' . rawurlencode($alias), true);
        } catch (\Throwable) {
            return null;
        }
    }

    /* ------------------------------------------------------------------
       Tile position: deliberately NOT a `PortalPlugin` (Stud.IP start page),
       but via `Navigation::addItem('/contents/quorum', …)` in the constructor
       under "My workplace" (`dispatch.php/contents/overview`). User flow:
       login → My workplace → Quorum tile → workplace full page with polls
       list, analogous to Courseware/Files.
       ------------------------------------------------------------------ */
}
