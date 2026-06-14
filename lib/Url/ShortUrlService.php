<?php

declare(strict_types=1);

namespace Quorum\Url;

use InvalidArgumentException;

/**
 * Single facade for poll code. Delegates to the `ShortUrlAdapter` chosen by
 * `AdapterDetector` and validates inputs at the entry layer (server-side
 * input validation).
 */
final class ShortUrlService
{
    public function __construct(private readonly ShortUrlAdapter $adapter)
    {
    }

    /**
     * @throws InvalidArgumentException if `path` is not a relative Stud.IP URL.
     */
    public function create(string $path, string $userId, ?string $title = null): string
    {
        self::assertRelativeStudIpPath($path);

        return $this->adapter->create($path, $userId, $title);
    }

    public function resolve(string $alias): ?ShortUrlData
    {
        return $this->adapter->resolve($alias);
    }

    /**
     * Poll short links must point ONLY to relative Stud.IP URLs — otherwise
     * the plugin resolver would be an open redirect for phishing.
     */
    private static function assertRelativeStudIpPath(string $path): void
    {
        if ($path === '' || $path[0] === '/') {
            throw new InvalidArgumentException(
                'ShortUrl path muss relativ sein, nicht absolut (kein führender "/").',
            );
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            throw new InvalidArgumentException(
                'ShortUrl path muss relativ sein, kein absolutes Schema (http/https/...).',
            );
        }
        if (str_contains($path, '..')) {
            throw new InvalidArgumentException(
                'ShortUrl path darf keine Pfad-Traversierung enthalten.',
            );
        }
    }
}
