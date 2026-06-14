<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Read-only DTO for a peer-instruction compare chain.
 *
 * A chain is the root poll plus all follow-up polls (`parent_poll_id =
 * root.id`). The per-poll counts are kept in a separate map (not on the
 * Poll DTO itself) so the Poll contract stays stable — it is also used by
 * the polls app + lifecycle API, which need no counts.
 *
 * Order in `$polls`:
 *   - Index 0: root
 *   - Index 1..n: follow-up polls ascending by `mkdate`
 */
final class CompareChain
{
    /**
     * @param list<Poll>                          $polls
     * @param array<string, array<string, int>>   $counts  pollId → [optionId → count]
     */
    public function __construct(
        public readonly array $polls,
        public readonly array $counts,
    ) {
    }

    public function root(): Poll
    {
        return $this->polls[0];
    }

    /**
     * Follow-up polls (without the root). Empty array when the root has no
     * follow-up rounds yet.
     *
     * @return list<Poll>
     */
    public function rounds(): array
    {
        return array_values(array_slice($this->polls, 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $root      = $this->root();
        $rootArr   = $root->toApiArray();
        // Rounds carry their counts inline — this saves the frontend an extra
        // lookup when rendering the side panels.
        $roundsArr = [];
        foreach ($this->rounds() as $round) {
            $arr           = $round->toApiArray();
            $arr['is_active'] = $round->isActive;
            $arr['mkdate']    = $round->mkdate;
            $arr['counts']    = $this->counts[$round->id] ?? [];
            $roundsArr[]      = $arr;
        }
        // Root counts separately — otherwise the frontend would have to guess
        // `root.counts` when the root itself is not yet in compare mode.
        return [
            'root'        => array_merge($rootArr, [
                'is_active' => $root->isActive,
                'mkdate'    => $root->mkdate,
            ]),
            'rounds'      => $roundsArr,
            'root_counts' => $this->counts[$root->id] ?? [],
        ];
    }
}
