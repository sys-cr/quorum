<?php

declare(strict_types=1);

namespace Quorum\Polls;

use Quorum\Polls\Exceptions\InvalidResponseException;

/**
 * (De)serialization of a survey DEFINITION as portable JSON — for the
 * "download survey" → "import survey" round trip (sharing/reusing across
 * courses, colleagues, instances).
 *
 * Deliberately ONLY the definition (question, question type, answer options),
 * NO responses and NO runtime data: an import creates a fresh survey with 0
 * responses. A source's absolute `expiresAt` is not a reusable duration limit
 * and is therefore not exported.
 *
 * Plain-string error messages (no `_()`) — consistent with the rest of the
 * service layer and unit-testable without a Stud.IP dependency.
 */
final class SurveyDefinition
{
    /** Magic marker in the JSON by which the import recognizes a Quorum file. */
    public const FORMAT  = 'quorum.survey';
    public const VERSION = 1;

    /**
     * Pure definition payload of a poll (question/type/option labels) —
     * without a file wrapper. Used both here and by `CollectionDefinition`
     * (per member survey).
     *
     * @return array{question: string, type: string, options: list<string>}
     */
    public static function pollToPayload(Poll $poll): array
    {
        return [
            'question' => $poll->question,
            'type'     => $poll->type,
            // Labels only — IDs are source-instance-specific and meaningless
            // on import (createPoll assigns fresh IDs).
            'options'  => array_values(array_map(
                static fn(array $o): string => (string) $o['label'],
                $poll->options
            )),
        ];
    }

    /**
     * Like {@see pollToPayload}, but for the associative poll row from
     * `CollectionsRepository::findPollsInCollection` (no Poll DTO). There
     * `options` is the decoded options list (`[{id,label}, …]`); free text = `[]`.
     *
     * @param array<string, mixed> $row
     * @return array{question: string, type: string, options: list<string>}
     */
    public static function rowToPayload(array $row): array
    {
        return [
            'question' => (string) ($row['question'] ?? ''),
            'type'     => (string) ($row['type'] ?? ''),
            'options'  => array_values(array_map(
                static fn($o): string => (string) (is_array($o) ? ($o['label'] ?? '') : $o),
                (array) ($row['options'] ?? [])
            )),
        ];
    }

    /**
     * Validates and normalizes ONE imported survey payload (file level:
     * importable type?). Content validation (question not empty, ≥ 2 options)
     * stays in `PollsService::createPoll` — single source of truth. Also used
     * per member survey by `CollectionDefinition::fromJson`.
     *
     * @param array<string, mixed> $survey
     * @return array{question: string, type: string, options: list<array{label: string}>}
     * @throws InvalidResponseException on non-importable type
     */
    public static function normalizeImportable(array $survey): array
    {
        $type = (string) ($survey['type'] ?? '');
        // Only the types supported by the flat create path — matrix has a
        // row/scale structure this lean definition cannot carry
        // (see PollType::SIMPLE_FORM).
        if (!in_array($type, PollType::SIMPLE_FORM, true)) {
            throw new InvalidResponseException('Dieser Fragetyp kann nicht importiert werden (möglich sind Multiple Choice, Skala, Emoji, Freitext).');
        }

        $options = [];
        if ($type !== PollType::FREITEXT) {
            foreach ((array) ($survey['options'] ?? []) as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $options[] = ['label' => $label];
                }
            }
        }

        return [
            'question' => trim((string) ($survey['question'] ?? '')),
            'type'     => $type,
            'options'  => $options,
        ];
    }

    /**
     * Serializes a poll definition to pretty, UTF-8-preserving JSON.
     */
    public static function toJson(Poll $poll, int $exportedAt): string
    {
        $payload = [
            'format'       => self::FORMAT,
            'version'      => self::VERSION,
            ...self::pollToPayload($poll),
            'exported_at'  => $exportedAt,
            'source_token' => $poll->token,
        ];

        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Parses and validates an imported JSON definition at FILE level (is it a
     * Quorum file? known version? importable type?). Content validation
     * (question not empty, ≥ 2 options) stays in `PollsService::createPoll`
     * so there is a single source of truth.
     *
     * @return array{question: string, type: string, options: list<array{label: string}>}
     * @throws InvalidResponseException on a broken/foreign/non-importable file
     */
    public static function fromJson(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidResponseException('Die Datei ist keine gültige Quorum-Umfrage (kein JSON).');
        }
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new InvalidResponseException('Die Datei ist keine Quorum-Umfrage-Definition.');
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new InvalidResponseException('Diese Datei wurde mit einer neueren Quorum-Version erstellt und kann hier nicht importiert werden.');
        }

        return self::normalizeImportable($data);
    }
}
