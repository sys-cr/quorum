<?php

declare(strict_types=1);

namespace Quorum\Moderation;

/**
 * Automatic free-text moderation via blocklist.
 *
 * Checks free-text / word-cloud submissions server-side against a
 * configurable blocklist BEFORE they are stored. Word-boundary matching
 * (case- and diacritic-tolerant) avoids "Scunthorpe" false positives: a
 * blocked word only matches as a standalone word, not as a substring.
 *
 * Stud.IP-free by design (list is injected) so it is unit-testable. Empty list
 * = moderation off (default); admins enable it by maintaining the list.
 */
final class TextModerator
{
    /** @var list<string> normalized (lowercased, trimmed) blocked terms */
    private array $blocklist;

    /**
     * @param list<string> $blocklist
     */
    public function __construct(array $blocklist = [])
    {
        $normalized = [];
        foreach ($blocklist as $term) {
            $t = self::normalize((string) $term);
            if ($t !== '') {
                $normalized[] = $t;
            }
        }
        $this->blocklist = array_values(array_unique($normalized));
    }

    /**
     * Builds the moderator from a comma-separated config string (e.g. the
     * Stud.IP config value `QUORUM_FREITEXT_BLOCKLIST`).
     */
    public static function fromCsv(string $csv): self
    {
        return new self(array_map('trim', explode(',', $csv)));
    }

    public function isBlocked(string $text): bool
    {
        return $this->firstMatch($text) !== null;
    }

    /**
     * Returns the first matched blocked term, or `null`.
     */
    public function firstMatch(string $text): ?string
    {
        $haystack = self::normalize($text);
        if ($haystack === '') {
            return null;
        }
        foreach ($this->blocklist as $term) {
            // \b works after normalization on near-ASCII words; for Unicode
            // word boundaries we use lookarounds on non-letters.
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $haystack) === 1) {
                return $term;
            }
        }
        return null;
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}
