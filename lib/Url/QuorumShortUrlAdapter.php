<?php

declare(strict_types=1);

namespace Quorum\Url;

use Quorum\Models\QuorumShortUrl;

/**
 * Persistence adapter for the Quorum-owned fallback table
 * `quorum_short_urls` (Stud.IP 6.0/6.1).
 */
final class QuorumShortUrlAdapter implements ShortUrlAdapter
{
    public function create(string $path, string $userId, ?string $title): string
    {
        $existing = QuorumShortUrl::findOneBySQL(
            '`path` = ? AND `user_id` = ?',
            [$path, $userId],
        );
        if ($existing) {
            return $existing->alias;
        }

        $entry = QuorumShortUrl::build([
            'alias'   => self::generateAlias(),
            'path'    => $path,
            'title'   => $title ?? '',
            'user_id' => $userId,
        ]);
        $entry->store();

        return $entry->alias;
    }

    public function resolve(string $alias): ?ShortUrlData
    {
        $row = QuorumShortUrl::findOneBySQL('alias = ?', [$alias]);
        return $row ? self::toData($row) : null;
    }

    public function existsAlias(string $alias): bool
    {
        return QuorumShortUrl::countBySql('alias = ?', [$alias]) > 0;
    }

    public function iterateAll(): iterable
    {
        foreach (QuorumShortUrl::findBySQL('1 ORDER BY `id` ASC') as $row) {
            yield self::toData($row);
        }
    }

    public function deleteByAlias(string $alias): void
    {
        QuorumShortUrl::deleteBySQL('alias = ?', [$alias]);
    }

    public function import(ShortUrlData $data): void
    {
        QuorumShortUrl::build([
            'alias'   => $data->alias,
            'path'    => $data->path,
            'title'   => $data->title ?? '',
            'user_id' => $data->userId,
        ])->store();
    }

    /**
     * Generates a short, URL-safe alias. 8 base62 chars (~47 bits of entropy)
     * suffice for ~280 billion aliases without collision; collisions are
     * additionally caught by the DB's UNIQUE index.
     */
    private static function generateAlias(): string
    {
        $chars   = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $alias   = '';
        for ($i = 0; $i < 8; $i++) {
            $alias .= $chars[random_int(0, 61)];
        }
        return $alias;
    }

    private static function toData(QuorumShortUrl $row): ShortUrlData
    {
        return new ShortUrlData(
            id:     (string) $row->id,
            alias:  $row->alias,
            path:   $row->path,
            title:  $row->title === '' ? null : $row->title,
            userId: $row->user_id,
            mkdate: (int) $row->mkdate,
            chdate: (int) $row->chdate,
        );
    }
}
