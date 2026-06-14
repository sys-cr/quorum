<?php

declare(strict_types=1);

namespace Quorum\Url;

/**
 * Immutable DTO for a short-link record.
 *
 * Fields mirror the schema of Stud.IP `short_urls` (6.2+). The ID is a
 * `string` because both native (auto_increment int) and Quorum
 * (auto_increment int) may return it as a string — the poll layer only needs
 * the identity, no arithmetic.
 */
final class ShortUrlData
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $alias,
        public readonly string  $path,
        public readonly ?string $title,
        public readonly string  $userId,
        public readonly int     $mkdate,
        public readonly int     $chdate,
    ) {
    }
}
