<?php

declare(strict_types=1);

namespace Quorum\Url;

/**
 * Canonical (relative) Stud.IP path for poll short links.
 *
 * Single source of truth for the short-link target: the anonymous voting page
 * `PController::show_action` (route `p/show/{token}`). The path is
 * deliberately RELATIVE (no leading slash, no scheme, no traversal) — exactly
 * what `ShortUrlService::create()` requires (open-redirect protection), and
 * `URLHelper::getURL()` in the resolver (`UController::r_action`) builds the
 * absolute target URL from it.
 *
 * Stud.IP-free by design (string building only) so it is unit-testable
 * without a bootstrap.
 */
final class PollLink
{
    /** Plugin route segment (= lowercased plugin class name). */
    public const PLUGIN_ROUTE = 'quorumstudipplugin';

    /**
     * Relative path of the anonymous voting page for a poll token. Passed as
     * `path` to `ShortUrlService::create()` and resolved back to an absolute
     * URL by the resolver.
     */
    public static function sharePath(string $token): string
    {
        return 'plugins.php/' . self::PLUGIN_ROUTE . '/p/show/' . $token;
    }
}
