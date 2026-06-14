<?php

declare(strict_types=1);

namespace Quorum\Models;

/**
 * SimpleORMap model for `quorum_responses`.
 *
 * Anonymous responses: no client- or person-related attribute is stored.
 *
 * @property string $id
 * @property string $poll_id
 * @property string $response       JSON-encoded
 * @property int    $mkdate
 */
final class QuorumResponse extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'quorum_responses';
        parent::configure($config);
    }
}
