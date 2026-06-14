<?php

declare(strict_types=1);

namespace Quorum\Models;

/**
 * SimpleORMap model for the Quorum fallback table `quorum_short_urls`.
 *
 * @property int    $id
 * @property string $alias
 * @property string $path
 * @property string $title
 * @property string $user_id
 * @property int    $mkdate
 * @property int    $chdate
 */
final class QuorumShortUrl extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'quorum_short_urls';
        parent::configure($config);
    }
}
