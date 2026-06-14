<?php

declare(strict_types=1);

namespace Quorum\Url;

/**
 * Persistence adapter for Stud.IP `short_urls` (6.2+).
 *
 * Writes through the Stud.IP `ShortUrl::create()` API so poll short links
 * automatically appear in the teacher's "Workplace → My short links" widget
 * and are manageable through Stud.IP's own UI.
 */
final class NativeShortUrlAdapter implements ShortUrlAdapter
{
    public function create(string $path, string $userId, ?string $title): string
    {
        $existing = \ShortUrl::findOneBySQL(
            '`path` = ? AND `user_id` = ?',
            [$path, $userId],
        );
        if ($existing) {
            return $existing->alias;
        }

        $row = \ShortUrl::create([
            'alias'   => self::generateAlias(),
            'path'    => $path,
            'title'   => $title ?? '',
            'user_id' => $userId,
        ]);

        return $row->alias;
    }

    public function resolve(string $alias): ?ShortUrlData
    {
        $row = \ShortUrl::findOneBySQL('alias = ?', [$alias]);
        return $row ? self::toData($row) : null;
    }

    public function existsAlias(string $alias): bool
    {
        return \ShortUrl::countBySql('alias = ?', [$alias]) > 0;
    }

    public function iterateAll(): iterable
    {
        foreach (\ShortUrl::findBySQL('1 ORDER BY `id` ASC') as $row) {
            yield self::toData($row);
        }
    }

    public function deleteByAlias(string $alias): void
    {
        \ShortUrl::deleteBySQL('alias = ?', [$alias]);
    }

    public function import(ShortUrlData $data): void
    {
        \ShortUrl::create([
            'alias'   => $data->alias,
            'path'    => $data->path,
            'title'   => $data->title ?? '',
            'user_id' => $data->userId,
        ]);
    }

    private static function generateAlias(): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $alias = '';
        for ($i = 0; $i < 8; $i++) {
            $alias .= $chars[random_int(0, 61)];
        }
        return $alias;
    }

    private static function toData(\ShortUrl $row): ShortUrlData
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
