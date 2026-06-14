<?php

declare(strict_types=1);

namespace Quorum\Polls;

use Quorum\Polls\Exceptions\InvalidResponseException;

/**
 * (De)serialization of a COLLECTION definition as portable JSON — the
 * "download collection" → "import collection" round trip. Analogous to
 * {@see SurveyDefinition}, one level up: name + description + the ordered
 * member surveys (each as a pure definition).
 *
 * Deliberately ONLY definitions, NO responses/runtime data: an import
 * creates a fresh collection with fresh surveys (0 responses). The survey
 * order is preserved.
 */
final class CollectionDefinition
{
    public const FORMAT  = 'quorum.collection';
    public const VERSION = 1;

    /**
     * @param list<array<string, mixed>> $pollRows member surveys in order —
     *        the associative rows from
     *        `CollectionsRepository::findPollsInCollection`.
     */
    public static function toJson(Collection $collection, array $pollRows, int $exportedAt): string
    {
        $payload = [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'name'        => $collection->name,
            'description' => $collection->description,
            'surveys'     => array_values(array_map(
                static fn(array $row): array => SurveyDefinition::rowToPayload($row),
                $pollRows
            )),
            'exported_at' => $exportedAt,
        ];

        return (string) json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Parses and validates an imported collection file. Each member survey
     * goes through the same normalization as the single import
     * ({@see SurveyDefinition::normalizeImportable}). A non-importable survey
     * (e.g. matrix) fails the entire import rather than creating a half collection.
     *
     * @return array{name: string, description: ?string, surveys: list<array{question: string, type: string, options: list<array{label: string}>}>}
     * @throws InvalidResponseException
     */
    public static function fromJson(string $raw): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new InvalidResponseException('Die Datei ist keine gültige Quorum-Sammlung (kein JSON).');
        }
        self::assertEnvelope($data);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidResponseException('Die importierte Sammlung hat keinen Namen.');
        }

        return [
            'name'        => $name,
            'description' => self::parseDescription($data),
            'surveys'     => self::parseSurveys($data),
        ];
    }

    /**
     * Checks the envelope's format marker + version ceiling. Throws on a
     * foreign format or a too-new file — unchanged behavior.
     *
     * @param array<string,mixed> $data
     * @throws InvalidResponseException
     */
    private static function assertEnvelope(array $data): void
    {
        if (($data['format'] ?? null) !== self::FORMAT) {
            throw new InvalidResponseException('Die Datei ist keine Quorum-Sammlungs-Definition.');
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new InvalidResponseException('Diese Datei wurde mit einer neueren Quorum-Version erstellt und kann hier nicht importiert werden.');
        }
    }

    /**
     * Normalizes the ordered member surveys. Each survey goes through the same
     * normalization as the single import; an empty list fails the import.
     *
     * @param array<string,mixed> $data
     * @return list<array{question: string, type: string, options: list<array{label: string}>}>
     * @throws InvalidResponseException
     */
    private static function parseSurveys(array $data): array
    {
        $rawSurveys = $data['surveys'] ?? null;
        if (!is_array($rawSurveys) || $rawSurveys === []) {
            throw new InvalidResponseException('Die Sammlung enthält keine Umfragen.');
        }
        $surveys = [];
        foreach ($rawSurveys as $survey) {
            $surveys[] = SurveyDefinition::normalizeImportable(is_array($survey) ? $survey : []);
        }
        return $surveys;
    }

    /**
     * Optional description: trimmed, empty string → null.
     *
     * @param array<string,mixed> $data
     */
    private static function parseDescription(array $data): ?string
    {
        $description = isset($data['description']) && $data['description'] !== null
            ? trim((string) $data['description'])
            : null;
        return $description === '' ? null : $description;
    }
}
