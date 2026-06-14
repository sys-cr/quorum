<?php

declare(strict_types=1);

/**
 * Voting timer / time window.
 *
 * Adds `expires_at` (Unix seconds, nullable) to `quorum_polls`: the optional
 * server-side auto-stop time. NULL = no time limit.
 *
 * Additive and nullable; existing polls are unchanged. The `expires_at` index
 * speeds up a cron auto-stop query (`WHERE is_active = 1 AND expires_at <= ?`).
 *
 * Idempotent via `INFORMATION_SCHEMA` lookup.
 */
final class QuorumPollsExpiresAt extends Migration
{
    public function description(): string
    {
        return 'Quorum: Abstimmungs-Timer (expires_at).';
    }

    public function up(): void
    {
        if (!$this->columnExists('quorum_polls', 'expires_at')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls`
                    ADD COLUMN `expires_at` INT NULL DEFAULT NULL AFTER `archived_at`,
                    ADD KEY `expires_at_idx` (`expires_at`)'
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists('quorum_polls', 'expires_at_idx')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP INDEX `expires_at_idx`');
        }
        if ($this->columnExists('quorum_polls', 'expires_at')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP COLUMN `expires_at`');
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
