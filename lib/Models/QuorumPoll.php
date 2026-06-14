<?php

declare(strict_types=1);

namespace Quorum\Models;

/**
 * SimpleORMap model for `quorum_polls`.
 *
 * @property string      $id
 * @property string      $token
 * @property string|null $seminar_id           nullable (global polls)
 * @property string      $user_id
 * @property string      $question
 * @property string      $type                 PollType value (mc/scales/emoji/freitext/matrix)
 * @property string      $options              JSON-encoded (`PollsRepository` handles decode/encode)
 * @property int         $is_active
 * @property int         $mkdate
 * @property int         $chdate
 * @property int|null    $archived_at          soft-delete timestamp
 * @property int|null    $expires_at           auto-stop timestamp (Unix seconds)
 * @property string|null $parent_poll_id       root of a compare chain
 * @property string|null $collection_id        collection membership
 * @property int|null    $collection_position  position within the collection
 */
final class QuorumPoll extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'quorum_polls';
        parent::configure($config);
    }
}
