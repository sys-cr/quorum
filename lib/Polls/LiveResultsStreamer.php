<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Generates SSE event strings for a voting ID.
 *
 * Deliberately decoupled from the HTTP layer (no direct `echo`/`flush`): the
 * controller calls `formatEvent()` and `formatHeartbeat()` and writes the
 * result into the response stream itself. This makes the class unit-testable
 * without a long-running request.
 *
 * `aggregateCounts()` delegates to `PollsRepository`, which encapsulates the
 * SQL aggregation. The streamer only sees the result array
 * `option ID => count` — no PII.
 */
final class LiveResultsStreamer
{
    public function __construct(private readonly PollsRepository $repo)
    {
    }

    /**
     * @return array<string, int> option ID → absolute vote count
     */
    public function aggregateCounts(string $pollId): array
    {
        return $this->repo->aggregateCountsForPoll($pollId);
    }

    /**
     * Formats an SSE event string. Default event name is "counts".
     *
     * Format (https://html.spec.whatwg.org/multipage/server-sent-events.html):
     *
     *     event: counts
     *     data: {"a":12,"b":8}
     *     <blank newline>
     *
     * @param array<string,int> $counts aggregated counts without PII
     */
    public function formatEvent(array $counts, ?string $eventName = null): string
    {
        $name    = $eventName ?? 'counts';
        $payload = json_encode($counts, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = '{}';
        }
        return "event: {$name}\n"
             . "data: {$payload}\n\n";
    }

    /**
     * Heartbeat event — keeps reverse proxies from closing the connection
     * due to inactivity. Typically sent every 30 s.
     */
    public function formatHeartbeat(): string
    {
        return "event: heartbeat\n"
             . "data: {}\n\n";
    }
}
