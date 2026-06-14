<?php

declare(strict_types=1);

namespace Quorum\Export;

/**
 * Results export as PDF HTML (per user feedback).
 *
 * Builds the HTML fragment that the controller renders through Stud.IP's
 * `\ExportPDF` (TCPDF) — deliberately Stud.IP-free (pure data→string
 * transformation) so it stays unit-testable. All escaping happens here
 * (question/options are user plaintext); the controller passes the result
 * to `writeHTML()` unchanged.
 */
final class ResultsPdfExporter
{
    /**
     * @param list<array{id: string, label: string}> $options
     * @param array<string, int>                     $counts   optionId => votes
     * @param array{0: string, 1: string, 2: string} $headers  column headers (i18n)
     * @param string                                 $metaLine e.g. "12 Stimmen · Stand: 13.06.2026 10:00"
     */
    public function buildHtml(
        string $question,
        array $options,
        array $counts,
        array $headers,
        string $metaLine,
    ): string {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $total = 0;
        foreach ($counts as $n) {
            $total += (int) $n;
        }

        $rows = '';
        foreach ($options as $opt) {
            $votes = (int) ($counts[$opt['id']] ?? 0);
            $pct   = $total > 0 ? $votes / $total * 100 : 0.0;
            $rows .= '<tr>'
                . '<td>' . $e((string) $opt['label']) . '</td>'
                . '<td align="right">' . $votes . '</td>'
                . '<td align="right">' . number_format($pct, 1, '.', '') . ' %</td>'
                . '</tr>';
        }

        // `border`/`cellpadding` instead of CSS: TCPDF's HTML subset only
        // handles the classic attributes reliably.
        return self::heading($question, $metaLine)
            . '<table border="1" cellpadding="4">'
            . '<tr>'
            . '<th><b>' . $e($headers[0]) . '</b></th>'
            . '<th align="right"><b>' . $e($headers[1]) . '</b></th>'
            . '<th align="right"><b>' . $e($headers[2]) . '</b></th>'
            . '</tr>'
            . $rows
            . '</table>';
    }

    /**
     * Free text: the responses as a single-column table (TCPDF renders lists
     * unreliably; a table stays readable everywhere).
     *
     * @param list<string> $responses
     */
    public function buildFreitextHtml(
        string $question,
        array $responses,
        string $header,
        string $metaLine,
    ): string {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($responses as $answer) {
            $rows .= '<tr><td>' . $e((string) $answer) . '</td></tr>';
        }

        return self::heading($question, $metaLine)
            . '<table border="1" cellpadding="4">'
            . '<tr><th><b>' . $e($header) . '</b></th></tr>'
            . $rows
            . '</table>';
    }

    /**
     * Matrix: rows × scale with a count per cell; missing cells count as 0.
     *
     * @param list<array{id: string, label: string}> $rows
     * @param list<array{id: string, label: string}> $scale
     * @param array<string, array<string, int>>      $counts
     */
    public function buildMatrixHtml(
        string $question,
        array $rows,
        array $scale,
        array $counts,
        string $metaLine,
    ): string {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $head = '<tr><th></th>';
        foreach ($scale as $s) {
            $head .= '<th align="right"><b>' . $e((string) $s['label']) . '</b></th>';
        }
        $head .= '</tr>';

        $body = '';
        foreach ($rows as $r) {
            $body .= '<tr><th><b>' . $e((string) $r['label']) . '</b></th>';
            foreach ($scale as $s) {
                $body .= '<td align="right">' . (int) ($counts[$r['id']][$s['id']] ?? 0) . '</td>';
            }
            $body .= '</tr>';
        }

        return self::heading($question, $metaLine)
            . '<table border="1" cellpadding="4">'
            . $head
            . $body
            . '</table>';
    }

    /** Shared head: question as H1 plus optional meta line. */
    private static function heading(string $question, string $metaLine): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<h1>' . $e($question) . '</h1>'
            . ($metaLine !== '' ? '<p>' . $e($metaLine) . '</p>' : '');
    }
}
