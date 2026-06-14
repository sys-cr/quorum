<?php

declare(strict_types=1);

namespace Quorum\Demo;

use Quorum\Polls\CollectionsRepository;
use Quorum\Polls\CollectionsService;
use Quorum\Polls\PollsRepository;
use Quorum\Polls\PollsService;
use Quorum\Polls\PollType;
use Quorum\Polls\Response;

/**
 * Seeds a person's archive once with example content: one poll per question
 * type (with responses) plus a demo collection containing several polls. Pure
 * showcase material — everything archived and finished, can be reactivated
 * and remodeled at any time.
 *
 * Idempotent: if the demo collection already exists, nothing happens
 * (prevents duplicates on repeated clicks).
 *
 * The content is described index-based (responses reference option/row/scale
 * indices, not concrete IDs) and is mapped to the actually generated poll IDs
 * on creation — independent of the internal ID scheme.
 */
final class DemoContentSeeder
{
    /** Anchor for the idempotency check. */
    public const COLLECTION_NAME = 'Demo-Sammlung: Vorlesung Zellbiologie';

    public function __construct(
        private readonly PollsService $polls,
        private readonly PollsRepository $pollsRepo,
        private readonly CollectionsService $collections,
        private readonly CollectionsRepository $collectionsRepo,
    ) {
    }

    /**
     * @return array{alreadyLoaded: bool, pollCount: int}
     */
    public function seedFor(string $userId): array
    {
        foreach ($this->collectionsRepo->findSummariesByUser($userId, 'all') as $existing) {
            if ($existing->name === self::COLLECTION_NAME) {
                return ['alreadyLoaded' => true, 'pollCount' => 0];
            }
        }

        $count = 0;

        // Standalone example polls — one per question type, archived.
        foreach (self::standalonePolls() as $spec) {
            $pollId = $this->createPoll($userId, $spec);
            $this->polls->finishPoll($pollId);
            $this->polls->archivePoll($pollId);
            $count++;
        }

        // Demo collection with several polls (presenter mode for browsing).
        $collection = $this->collections->createCollection(
            $userId,
            self::COLLECTION_NAME,
            'Beispiel-Sammlung mit gemischten Fragetypen — zeigt den Presenter-Modus. '
            . 'Archiviert; jederzeit reaktivierbar.',
        );
        $position = 0;
        foreach (self::collectionPolls() as $spec) {
            $pollId = $this->createPoll($userId, $spec);
            $this->polls->finishPoll($pollId);
            $this->collections->addPollToCollection($collection->id, $pollId, $userId, $position++);
            $count++;
        }
        $this->collections->archiveCollection($collection->id);

        return ['alreadyLoaded' => false, 'pollCount' => $count];
    }

    /**
     * Creates a poll and writes the demo responses directly (validated
     * payloads, timestamps spread over the last hour for a realistic picture).
     *
     * Quiz demo (`quiz => true`): enables quiz mode and fills the leaderboard —
     * the first responses of the spec are correct, sorted, and carry a
     * pseudonym + demo score (`leaderboard`); the rest stay anonymous.
     *
     * @param array{type: string, question: string, options: array<mixed>, responses: list<mixed>, quiz?: bool, leaderboard?: list<array{0: string, 1: int}>} $spec
     */
    private function createPoll(string $userId, array $spec): string
    {
        $quiz = !empty($spec['quiz']);
        $poll = $this->polls->createPoll(
            $userId, $spec['question'], $spec['type'], $spec['options'], quizMode: $quiz,
        );

        $payloads    = $this->payloadsFor($spec['type'], $poll->options, $spec['responses']);
        $leaderboard = $spec['leaderboard'] ?? [];
        $total       = max(1, count($payloads));
        $base        = time() - 3600;
        foreach (array_values($payloads) as $i => $payload) {
            [$nickname, $score] = $leaderboard[$i] ?? [null, null];
            $this->pollsRepo->saveResponse(new Response(
                id:       bin2hex(random_bytes(16)),
                pollId:   $poll->id,
                payload:  $payload,
                mkdate:   (int) ($base + ($i * 3600 / $total)),
                nickname: $nickname,
                score:    $score,
            ));
        }

        return $poll->id;
    }

    /**
     * Maps the index-based response specs to the concrete option IDs of the
     * just-created poll.
     *
     * @param array<mixed> $options  normalized poll options
     * @param list<mixed>  $responses
     * @return list<array<string,mixed>>
     */
    private function payloadsFor(string $type, array $options, array $responses): array
    {
        if ($type === PollType::FREITEXT) {
            return array_map(static fn(string $text): array => ['text' => $text], $responses);
        }

        if ($type === PollType::MATRIX) {
            $rowIds   = array_column($options['rows']  ?? [], 'id');
            $scaleIds = array_column($options['scale'] ?? [], 'id');
            $out = [];
            foreach ($responses as $perRowScaleIdx) {
                $choices = [];
                foreach ($perRowScaleIdx as $rowIdx => $scaleIdx) {
                    $choices[$rowIds[$rowIdx]] = $scaleIds[$scaleIdx];
                }
                $out[] = ['choices' => $choices];
            }
            return $out;
        }

        $optionIds = array_column($options, 'id');

        if ($type === PollType::MULTI) {
            return array_map(
                static fn(array $set): array => ['selected' => array_map(static fn(int $i): string => $optionIds[$i], $set)],
                $responses,
            );
        }

        // SELECTION (mc / scales / emoji): one chosen index per response.
        return array_map(static fn(int $i): array => ['selected' => $optionIds[$i]], $responses);
    }

    /**
     * Expands a frequency distribution (votes per option index) into a flat
     * list of single indices. `expand([3, 1])` → `[0, 0, 0, 1]`.
     *
     * @param list<int> $distribution
     * @return list<int>
     */
    private static function expand(array $distribution): array
    {
        $out = [];
        foreach ($distribution as $index => $votes) {
            for ($i = 0; $i < $votes; $i++) {
                $out[] = $index;
            }
        }
        return $out;
    }

    /** @return list<array{type: string, question: string, options: array<mixed>, responses: list<mixed>}> */
    private static function standalonePolls(): array
    {
        return [
            [
                'type'     => PollType::MC,
                'question' => 'Welche Lernform hilft Ihnen am meisten beim Verstehen?',
                'options'  => [['label' => 'Vorlesung'], ['label' => 'Übung in Gruppen'], ['label' => 'Selbststudium'], ['label' => 'Praktikum']],
                'responses' => self::expand([7, 14, 5, 9]),
            ],
            [
                // Quiz example: single choice with exactly one correct answer,
                // quiz mode active and a prefilled (pseudonymous) leaderboard.
                'type'     => PollType::MC,
                'question' => 'Wie viele Chromosomen hat eine normale menschliche Körperzelle?',
                'options'  => [
                    ['label' => '23'],
                    ['label' => '46', 'correct' => true],
                    ['label' => '48'],
                    ['label' => '92'],
                ],
                'quiz'     => true,
                // Index 1 (= 46) is correct; the first six responses are correct
                // and carry the leaderboard, the rest is anonymous/mixed.
                'responses'   => [1, 1, 1, 1, 1, 1, 0, 2, 1, 3, 0, 1],
                'leaderboard' => [
                    ['Blitzmerker', 960], ['Ada L.', 900], ['Quizfuchs', 840],
                    ['Synapse', 720], ['Neuron', 610], ['Eulerin', 540],
                ],
            ],
            [
                'type'     => PollType::MULTI,
                'question' => 'Welche Themen möchten Sie in der Klausurvorbereitung vertiefen?',
                'options'  => [['label' => 'Genetik'], ['label' => 'Zellteilung'], ['label' => 'Stoffwechsel'], ['label' => 'Ökologie'], ['label' => 'Evolution']],
                'responses' => [[0, 1], [1, 2], [0], [1, 4], [2, 3], [0, 1, 2], [4], [1], [0, 2], [1, 3, 4], [2], [0, 1]],
            ],
            [
                'type'     => PollType::SCALES,
                'question' => 'Wie sicher fühlen Sie sich beim Thema Mitose?',
                'options'  => [['label' => 'Sehr unsicher'], ['label' => 'Eher unsicher'], ['label' => 'Neutral'], ['label' => 'Eher sicher'], ['label' => 'Sehr sicher']],
                'responses' => self::expand([2, 6, 8, 9, 4]),
            ],
            [
                'type'     => PollType::EMOJI,
                'question' => 'Wie ist Ihre Stimmung zum Vorlesungsbeginn?',
                'options'  => [['label' => '😀'], ['label' => '🙂'], ['label' => '😐'], ['label' => '😕'], ['label' => '😴']],
                'responses' => self::expand([6, 11, 7, 3, 2]),
            ],
            [
                'type'     => PollType::FREITEXT,
                'question' => 'Nennen Sie in einem Wort den wichtigsten Begriff der heutigen Sitzung.',
                'options'  => [],
                'responses' => [
                    'Mitose', 'Zellkern', 'DNA', 'Chromosom', 'Zellteilung', 'Mitochondrium',
                    'Mitose', 'Zellkern', 'Chromatid', 'Spindelapparat', 'DNA', 'Mitose',
                    'Replikation', 'Zellzyklus', 'Chromosom', 'Mitose',
                ],
            ],
            [
                'type'     => PollType::MATRIX,
                'question' => 'Wie bewerten Sie die folgenden Aspekte dieser Vorlesung?',
                'options'  => [
                    'rows'  => [['label' => 'Tempo'], ['label' => 'Verständlichkeit'], ['label' => 'Folien & Material'], ['label' => 'Beteiligung']],
                    'scale' => [['label' => '−−'], ['label' => '−'], ['label' => 'o'], ['label' => '+'], ['label' => '++']],
                ],
                // per response one scale index per row (pace, clarity, material, participation)
                'responses' => [
                    [2, 3, 4, 2], [3, 4, 3, 1], [2, 2, 4, 2], [1, 3, 3, 3],
                    [3, 4, 4, 2], [2, 3, 3, 1], [4, 4, 4, 3], [2, 2, 3, 2],
                    [3, 3, 4, 2], [1, 2, 3, 1],
                ],
            ],
        ];
    }

    /** @return list<array{type: string, question: string, options: array<mixed>, responses: list<mixed>}> */
    private static function collectionPolls(): array
    {
        return [
            [
                'type'     => PollType::MC,
                'question' => 'Welcher Zellbestandteil produziert den Großteil des ATP?',
                'options'  => [['label' => 'Zellkern'], ['label' => 'Mitochondrium'], ['label' => 'Ribosom'], ['label' => 'Golgi-Apparat']],
                'responses' => self::expand([3, 18, 4, 2]),
            ],
            [
                'type'     => PollType::MULTI,
                'question' => 'Welche Strukturen kommen typischerweise NUR in Pflanzenzellen vor?',
                'options'  => [['label' => 'Zellwand'], ['label' => 'Chloroplast'], ['label' => 'Mitochondrium'], ['label' => 'Große Zentralvakuole'], ['label' => 'Zellkern']],
                'responses' => [[0, 1, 3], [0, 1], [1, 3], [0, 1, 3], [0, 2], [1], [0, 1, 3], [0, 1, 2, 3], [1, 3], [0, 1]],
            ],
            [
                'type'     => PollType::EMOJI,
                'question' => 'Wie fanden Sie das Tempo der heutigen Sitzung?',
                'options'  => [['label' => '😀 genau richtig'], ['label' => '😐 ging so'], ['label' => '😕 zu schnell'], ['label' => '😴 zu langsam']],
                'responses' => self::expand([13, 5, 6, 1]),
            ],
            [
                'type'     => PollType::FREITEXT,
                'question' => 'Welche Frage ist für Sie nach heute noch offen?',
                'options'  => [],
                'responses' => [
                    'Unterschied Mitose/Meiose?', 'Wozu dient der Golgi-Apparat?',
                    'Wie genau läuft die Replikation ab?', 'Was passiert bei Fehlern in der Zellteilung?',
                    'Rolle der Telomere?', 'Unterschied Mitose/Meiose?', 'Wie entsteht Krebs aus Zellteilung?',
                ],
            ],
        ];
    }
}
