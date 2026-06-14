<?php

declare(strict_types=1);

/**
 * Polls lifecycle columns:
 *
 *   - `archived_at`    INT NULL          — soft-delete timestamp; NULL = active
 *                                          list, NOT NULL = archive.
 *   - `parent_poll_id` VARCHAR(32) NULL  — root poll of a compare chain.
 *
 * Both columns are additive and nullable; existing polls are unchanged.
 *
 * Indexes:
 *   - `archived_at_idx`     — speeds up the archive sidebar filter
 *   - `parent_poll_id_idx`  — self-join for compare chains
 *
 * Idempotent via `INFORMATION_SCHEMA.COLUMNS` lookup.
 */
final class QuorumPollsLifecycle extends Migration
{
    public function description(): string
    {
        return 'Quorum: Polls-Lifecycle (archived_at + parent_poll_id).';
    }

    public function up(): void
    {
        if (!$this->columnExists('quorum_polls', 'archived_at')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls`
                    ADD COLUMN `archived_at` INT NULL DEFAULT NULL AFTER `chdate`,
                    ADD KEY `archived_at_idx` (`archived_at`)'
            );
        }

        if (!$this->columnExists('quorum_polls', 'parent_poll_id')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls`
                    ADD COLUMN `parent_poll_id` VARCHAR(32) NULL DEFAULT NULL AFTER `user_id`,
                    ADD KEY `parent_poll_id_idx` (`parent_poll_id`)'
            );
        }
    }

    public function down(): void
    {
        // Check index and column existence separately: an aborted up() may
        // have left a column without its index (or vice versa), and a combined
        // `DROP INDEX … DROP COLUMN` would then fail and block the rollback.
        if ($this->indexExists('quorum_polls', 'archived_at_idx')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP INDEX `archived_at_idx`');
        }
        if ($this->columnExists('quorum_polls', 'archived_at')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP COLUMN `archived_at`');
        }

        if ($this->indexExists('quorum_polls', 'parent_poll_id_idx')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP INDEX `parent_poll_id_idx`');
        }
        if ($this->columnExists('quorum_polls', 'parent_poll_id')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP COLUMN `parent_poll_id`');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = ?
                AND COLUMN_NAME  = ?'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = \DBManager::get()->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = ?
                AND INDEX_NAME   = ?
              LIMIT 1'
        );
        $stmt->execute([$table, $index]);
        return (bool) $stmt->fetchColumn();
    }
}
