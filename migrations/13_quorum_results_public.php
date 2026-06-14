<?php

declare(strict_types=1);

/**
 * Result visibility for students (opt-out): flag on the poll. Default `1` =
 * public — closed polls appear in the student view of the course tab. Teachers
 * can set it to `0` per poll to hide the result review. Existing polls stay
 * public.
 *
 * Idempotent via `information_schema` (same style as migration 12).
 */
final class QuorumResultsPublic extends Migration
{
    public function description(): string
    {
        return 'Quorum: Ergebnis-Sichtbarkeit für Studierende (results_public, Opt-out, Default öffentlich).';
    }

    public function up(): void
    {
        $db = \DBManager::get();

        if (!$this->columnExists($db, 'quorum_polls', 'results_public')) {
            $db->exec("ALTER TABLE `quorum_polls` ADD COLUMN `results_public` tinyint(1) NOT NULL DEFAULT 1");
        }
    }

    public function down(): void
    {
        $db = \DBManager::get();

        if ($this->columnExists($db, 'quorum_polls', 'results_public')) {
            $db->exec('ALTER TABLE `quorum_polls` DROP COLUMN `results_public`');
        }
    }

    private function columnExists(\PDO $db, string $table, string $column): bool
    {
        return (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = " . $db->quote($table) . "
                AND column_name = " . $db->quote($column)
        )->fetchColumn() > 0;
    }
}
