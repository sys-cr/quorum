<?php

declare(strict_types=1);

namespace Quorum\Polls;

/**
 * Domain DTO for a single poll response.
 *
 * Anonymous votes carry no client- or person-related attribute — only the
 * poll reference, the payload and a timestamp. In quiz mode a freely chosen
 * nickname (opt-in, no real name) and the server-computed score are
 * optionally added.
 *
 * @phpstan-type ResponsePayload array<string,mixed>
 */
final class Response
{
    /**
     * @param ResponsePayload $payload
     */
    public function __construct(
        public readonly string  $id,
        public readonly string  $pollId,
        public readonly array   $payload,
        public readonly int     $mkdate,
        /** Nickname (opt-in, ≤ 40 characters) — null without quiz/opt-in. */
        public readonly ?string $nickname = null,
        /** Points from the QuizScorer — null outside quiz mode. */
        public readonly ?int    $score    = null,
    ) {
    }
}
