<?php

declare(strict_types=1);

namespace Quorum\Url;

use Closure;

/**
 * Selects the appropriate `ShortUrlAdapter` at plugin boot time.
 *
 * Three paths:
 *   - Stud.IP's `short_urls` missing       → QuorumShortUrlAdapter (6.0/6.1)
 *   - `short_urls` present, Quorum empty    → NativeShortUrlAdapter  (fresh 6.2)
 *   - `short_urls` present, Quorum has data → CompositeShortUrlAdapter (transition)
 *
 * The schema check + existing-count are injected as closures so unit tests
 * can run deterministically without a DB (see `AdapterDetectorTest`).
 */
final class AdapterDetector
{
    /** @var Closure(): bool */
    private Closure $shortUrlsTableExists;

    /** @var Closure(): int */
    private Closure $quorumShortUrlsCount;

    private ?ShortUrlAdapter $cached = null;

    /**
     * @param Closure(): bool $shortUrlsTableExists
     * @param Closure(): int  $quorumShortUrlsCount
     */
    public function __construct(Closure $shortUrlsTableExists, Closure $quorumShortUrlsCount)
    {
        $this->shortUrlsTableExists = $shortUrlsTableExists;
        $this->quorumShortUrlsCount = $quorumShortUrlsCount;
    }

    public function detect(): ShortUrlAdapter
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $native    = ($this->shortUrlsTableExists)();
        $quorumCnt = ($this->quorumShortUrlsCount)();

        if (!$native) {
            return $this->cached = new QuorumShortUrlAdapter();
        }

        if ($quorumCnt > 0) {
            return $this->cached = new CompositeShortUrlAdapter(
                primary:   new NativeShortUrlAdapter(),
                secondary: new QuorumShortUrlAdapter(),
            );
        }

        return $this->cached = new NativeShortUrlAdapter();
    }

    /**
     * Standard constructor for the production boot path: uses DBManager for
     * the live checks. Tests use the regular constructor with closures.
     */
    public static function fromDatabase(): self
    {
        return new self(
            static fn (): bool => self::tableExists('short_urls'),
            static fn (): int  => self::tableExists('quorum_short_urls')
                ? (int) \DBManager::get()
                    ->query('SELECT COUNT(*) FROM `quorum_short_urls`')
                    ->fetchColumn()
                : 0,
        );
    }

    private static function tableExists(string $table): bool
    {
        $stmt = \DBManager::get()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }
}
