<?php

declare(strict_types=1);

namespace Quorum\Templates;

use Quorum\Polls\Poll;
use Quorum\Polls\PollsService;

/**
 * Domain service for question-group templates (logic in the service, the
 * controller stays thin).
 *
 * A template is a reusable, course-INDEPENDENT definition: title + question
 * list (question/type/options) — WITHOUT IDs, tokens, responses or course
 * binding. Copying into a course is a deep copy: each template question
 * becomes a new, standalone poll in the target course.
 *
 * Tenant isolation + authorization live at the controller boundary: the
 * caller has verified `$userId` is a teacher in `$targetSeminarId`. The
 * service itself is course-agnostic.
 */
final class TemplateService
{
    public function __construct(private readonly PollsService $polls)
    {
    }

    /**
     * Extracts a reusable template structure from concrete polls (e.g. a
     * collection) — only definitions, no IDs/tokens/responses/course binding
     * (data-minimized).
     *
     * @param list<Poll> $polls
     * @return array{title: string, polls: list<array{question: string, type: string, options: array<mixed>}>}
     */
    public function buildFromPolls(string $title, array $polls): array
    {
        return [
            'title' => trim($title),
            'polls' => array_map(
                static fn (Poll $p): array => [
                    'question' => $p->question,
                    'type'     => $p->type,
                    'options'  => $p->options,
                ],
                array_values($polls),
            ),
        ];
    }

    /**
     * Copies a template as new polls into the target course (deep copy). Each
     * question is recreated via `PollsService::createPoll` (new token, target
     * seminar, calling user) — no responses are copied.
     *
     * @param array{title?: string, polls?: list<array{question?: string, type?: string, options?: array<mixed>}>} $template
     * @return list<Poll> the newly created polls
     */
    public function instantiate(array $template, string $targetSeminarId, string $userId): array
    {
        $created = [];
        foreach (($template['polls'] ?? []) as $def) {
            $created[] = $this->polls->createPoll(
                userId:    $userId,
                question:  (string) ($def['question'] ?? ''),
                type:      (string) ($def['type'] ?? 'mc'),
                options:   is_array($def['options'] ?? null) ? $def['options'] : [],
                seminarId: $targetSeminarId,
            );
        }
        return $created;
    }
}
