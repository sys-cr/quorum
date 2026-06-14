<?php

declare(strict_types=1);

namespace Quorum\Export;

/**
 * CSV export of aggregated voting results.
 *
 * Returns only anonymous aggregates (option, absolute votes, percent) — no
 * user attribution, no PII. RFC-4180-compliant (CRLF lines, quoting on special
 * characters, optional BOM for Excel).
 *
 * Stud.IP-free by design (pure data→string transformation) so it is unit-testable.
 */
final class ResultsCsvExporter
{
    /** UTF-8 BOM so Excel recognizes umlauts correctly. */
    public const BOM = "\xEF\xBB\xBF";

    /**
     * One poll as a CSV table: header row + one row per option.
     *
     * @param list<array{id: string, label: string}> $options
     * @param array<string, int>                     $counts   optionId => votes
     * @param array{0: string, 1: string, 2: string} $headers  column headers (i18n)
     */
    public function exportPoll(
        array $options,
        array $counts,
        array $headers = ['Option', 'Stimmen', 'Prozent'],
        bool $withBom = false,
    ): string {
        $rows = [$headers];
        $total = 0;
        foreach ($counts as $n) {
            $total += (int) $n;
        }
        foreach ($options as $opt) {
            $votes = (int) ($counts[$opt['id']] ?? 0);
            $rows[] = [
                (string) $opt['label'],
                (string) $votes,
                self::formatPercent($total > 0 ? $votes / $total * 100 : 0.0),
            ];
        }
        return ($withBom ? self::BOM : '') . self::toCsv($rows);
    }

    /**
     * Multiple polls (collection / compare chain) in one file: per poll a
     * title row with the question, then the table, separated by a blank line.
     *
     * @param list<array{question: string, options: list<array{id: string, label: string}>, counts: array<string, int>}> $polls
     * @param array{0: string, 1: string, 2: string} $headers
     */
    public function exportCollection(
        array $polls,
        array $headers = ['Option', 'Stimmen', 'Prozent'],
        bool $withBom = false,
    ): string {
        $blocks = [];
        foreach ($polls as $poll) {
            $title = self::toCsv([[(string) $poll['question']]]);
            $table = $this->exportPoll($poll['options'], $poll['counts'], $headers);
            $blocks[] = $title . $table;
        }
        return ($withBom ? self::BOM : '') . implode("\r\n", $blocks);
    }

    /**
     * Free-text responses as a single-column CSV list.
     *
     * @param list<string> $responses
     */
    public function exportFreitext(array $responses, string $header = 'Antwort', bool $withBom = false): string
    {
        $rows = [[$header]];
        foreach ($responses as $answer) {
            $rows[] = [(string) $answer];
        }
        return ($withBom ? self::BOM : '') . self::toCsv($rows);
    }

    /**
     * Matrix as a rows × scale table; missing cells count as 0.
     *
     * @param list<array{id: string, label: string}> $rows
     * @param list<array{id: string, label: string}> $scale
     * @param array<string, array<string, int>>      $counts
     */
    public function exportMatrix(array $rows, array $scale, array $counts, string $corner = '', bool $withBom = false): string
    {
        $head = [$corner];
        foreach ($scale as $s) {
            $head[] = (string) $s['label'];
        }
        $out = [$head];
        foreach ($rows as $r) {
            $line = [(string) $r['label']];
            foreach ($scale as $s) {
                $line[] = (string) (int) ($counts[$r['id']][$s['id']] ?? 0);
            }
            $out[] = $line;
        }
        return ($withBom ? self::BOM : '') . self::toCsv($out);
    }

    private static function formatPercent(float $pct): string
    {
        // Locale-independent (dot as decimal separator), 1 decimal place.
        return number_format($pct, 1, '.', '');
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function toCsv(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(self::escapeField(...), $row));
        }
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escapeField(string $field): string
    {
        if (preg_match('/[",\r\n]/', $field) === 1) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }
}
