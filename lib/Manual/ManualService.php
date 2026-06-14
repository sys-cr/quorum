<?php

declare(strict_types=1);

namespace Quorum\Manual;

/**
 * Provides the consolidated user manual as Markdown or HTML — depending on the
 * language (DE/EN) and the audience (teachers: complete; students: only the
 * "Teilnehmen" section). The source is one maintained Markdown file per language
 * under `docs/user/<lang>/`; the student section is delimited there with HTML
 * comment markers.
 *
 * The slice logic (`extract`) is pure and testable without a Stud.IP dependency;
 * only `html()` uses the Parsedown shipped with Stud.IP (with a fallback).
 */
final class ManualService
{
    public const AUDIENCE_TEACHER = 'teacher';
    public const AUDIENCE_STUDENT = 'student';

    private const STUDENT_START = '<!-- AUDIENCE:STUDENT:START -->';
    private const STUDENT_END   = '<!-- AUDIENCE:STUDENT:END -->';

    /** @param string $docsUserDir absolute path to `docs/user` */
    public function __construct(private readonly string $docsUserDir)
    {
    }

    /** Stud.IP locale (`de_DE`, `en_GB`) → manual language (`de`/`en`). */
    public static function langFromLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'en') ? 'en' : 'de';
    }

    /** Markdown of the manual for language + audience. */
    public function markdown(string $lang, string $audience): string
    {
        $file = $this->fileFor($lang);
        $md   = is_file($file) ? (string) file_get_contents($file) : '';

        return self::extract($md, $audience);
    }

    /** HTML of the manual (Markdown → HTML via Parsedown, with fallback). */
    public function html(string $lang, string $audience): string
    {
        $md = $this->markdown($lang, $audience);

        if (class_exists(\Parsedown::class)) {
            $parsedown = new \Parsedown();
            // Own, trusted content → keep inline HTML (anchors).
            return $parsedown->text($md);
        }

        // Fallback without Parsedown (CLI/test): raw, escaped text.
        return '<pre>' . htmlspecialchars($md, ENT_QUOTES) . '</pre>';
    }

    /**
     * Slices the Markdown down to the audience (pure function):
     *   - Teachers: complete document, only the audience markers removed.
     *   - Students: document title (first H1) + the "Teilnehmen" section
     *     delimited with markers.
     */
    public static function extract(string $markdown, string $audience): string
    {
        if ($audience !== self::AUDIENCE_STUDENT) {
            return trim(str_replace([self::STUDENT_START, self::STUDENT_END], '', $markdown)) . "\n";
        }

        $title = '';
        if (preg_match('/^\#\s+(.+)$/m', $markdown, $m) === 1) {
            $title = '# ' . trim($m[1]) . "\n\n";
        }

        $start = strpos($markdown, self::STUDENT_START);
        $end   = strpos($markdown, self::STUDENT_END);
        if ($start === false || $end === false || $end <= $start) {
            return trim($title) . "\n";
        }

        $blockStart = $start + strlen(self::STUDENT_START);
        $block      = substr($markdown, $blockStart, $end - $blockStart);

        return $title . trim($block) . "\n";
    }

    private function fileFor(string $lang): string
    {
        $rel = $lang === 'en' ? 'en/manual.md' : 'de/anleitung.md';
        return rtrim($this->docsUserDir, '/') . '/' . $rel;
    }
}
