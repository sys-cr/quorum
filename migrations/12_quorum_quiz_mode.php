<?php

declare(strict_types=1);

/**
 * Quiz mode: opt-in flag on the poll, nickname + server-side score on the
 * response.
 *
 * Also restores a `poll_id` index on `quorum_responses` — migration 11
 * removed the table's only index together with `poll_client`; since then all
 * aggregations (live counts, export, leaderboard) ran as full scans.
 * `poll_mkdate (poll_id, mkdate)` covers the access paths.
 *
 * Idempotent via `information_schema` (same style as migration 11).
 */
final class QuorumQuizMode extends Migration
{
    public function description(): string
    {
        return 'Quorum: Quiz-Modus (quiz_mode, nickname, score) + poll_id-Index auf quorum_responses.';
    }

    public function up(): void
    {
        $db = \DBManager::get();

        if (!$this->columnExists($db, 'quorum_polls', 'quiz_mode')) {
            $db->exec("ALTER TABLE `quorum_polls` ADD COLUMN `quiz_mode` tinyint(1) NOT NULL DEFAULT 0");
        }
        if (!$this->columnExists($db, 'quorum_responses', 'nickname')) {
            $db->exec("ALTER TABLE `quorum_responses` ADD COLUMN `nickname` varchar(40) DEFAULT NULL");
        }
        if (!$this->columnExists($db, 'quorum_responses', 'score')) {
            $db->exec("ALTER TABLE `quorum_responses` ADD COLUMN `score` int DEFAULT NULL");
        }
        if (!$this->indexExists($db, 'quorum_responses', 'poll_mkdate')) {
            $db->exec('ALTER TABLE `quorum_responses` ADD INDEX `poll_mkdate` (`poll_id`, `mkdate`)');
        }
    }

    public function down(): void
    {
        $db = \DBManager::get();

        if ($this->indexExists($db, 'quorum_responses', 'poll_mkdate')) {
            $db->exec('ALTER TABLE `quorum_responses` DROP INDEX `poll_mkdate`');
        }
        if ($this->columnExists($db, 'quorum_responses', 'score')) {
            $db->exec('ALTER TABLE `quorum_responses` DROP COLUMN `score`');
        }
        if ($this->columnExists($db, 'quorum_responses', 'nickname')) {
            $db->exec('ALTER TABLE `quorum_responses` DROP COLUMN `nickname`');
        }
        if ($this->columnExists($db, 'quorum_polls', 'quiz_mode')) {
            $db->exec('ALTER TABLE `quorum_polls` DROP COLUMN `quiz_mode`');
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

    private function indexExists(\PDO $db, string $table, string $index): bool
    {
        return (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = " . $db->quote($table) . "
                AND index_name = " . $db->quote($index)
        )->fetchColumn() > 0;
    }
}
