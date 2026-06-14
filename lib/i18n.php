<?php

declare(strict_types=1);

/**
 * Global translation helper for Quorum's own user-facing strings.
 *
 * Routes a German source string through the plugin's `quorum` gettext domain
 * (catalogs under `locale/<lang>/LC_MESSAGES/quorum.mo`) and falls back to the
 * Stud.IP core domain when the string is not in the plugin catalog. So
 * plugin-specific strings are translated by Quorum, while generic Stud.IP
 * terms (Cancel, Save, Course, …) inherit the core translation and are not
 * duplicated in the plugin catalog. German is the source language, so a German
 * session needs no catalog at all.
 *
 * Loaded eagerly from the plugin constructor (the SPL/Composer autoloader only
 * kicks in on the first `Quorum\…` class lookup, which is too late for a plain
 * function used directly in controllers and views). Guarded against ext-gettext
 * being absent so controllers/views stay usable in CLI/test contexts.
 */

if (!function_exists('_quorum')) {
    function _quorum(string $text): string
    {
        // No ext-gettext (CLI/test): defer to core `_()` or the source string.
        if (!function_exists('dgettext')) {
            return function_exists('_') ? _($text) : $text;
        }

        static $bound = false;
        if (!$bound) {
            bindtextdomain('quorum', __DIR__ . '/../locale');
            bind_textdomain_codeset('quorum', 'UTF-8');
            $bound = true;
        }

        $translated = dgettext('quorum', $text);
        // dgettext returns the msgid unchanged when the string is not in the
        // plugin catalog — then defer to the Stud.IP core domain.
        return $translated === $text ? _($text) : $translated;
    }
}
