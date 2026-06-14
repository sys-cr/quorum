<?php

declare(strict_types=1);

/**
 * Poll collections for presenter mode and the task-group workflow.
 *
 *   1. New table `quorum_poll_collections` (id, user_id, name, description,
 *      mkdate, chdate, archived_at).
 *   2. Column `collection_id` on `quorum_polls` (nullable FK without
 *      constraint — cleanup handled in the service).
 *   3. Column `collection_position` on `quorum_polls` (nullable INT — order
 *      within the collection).
 *
 * Idempotent via `INFORMATION_SCHEMA` lookup.
 */
final class QuorumPollCollections extends Migration
{
    public function description(): string
    {
        return 'Quorum: Sammlungen + Mitgliedschaft.';
    }

    public function up(): void
    {
        \DBManager::get()->exec(
            'CREATE TABLE IF NOT EXISTS `quorum_poll_collections` (
                `id`          varchar(32)      NOT NULL,
                `user_id`     varchar(32)      NOT NULL,
                `name`        varchar(255)     NOT NULL,
                `description` text             DEFAULT NULL,
                `mkdate`      int(11) unsigned NOT NULL,
                `chdate`      int(11) unsigned NOT NULL,
                `archived_at` int(11)          DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user` (`user_id`),
                KEY `archived_at_idx` (`archived_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        if (!$this->columnExists('quorum_polls', 'collection_id')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls`
                    ADD COLUMN `collection_id` varchar(32) NULL DEFAULT NULL AFTER `parent_poll_id`,
                    ADD KEY `collection_id_idx` (`collection_id`)'
            );
        }
        if (!$this->columnExists('quorum_polls', 'collection_position')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls`
                    ADD COLUMN `collection_position` int(11) NULL DEFAULT NULL AFTER `collection_id`'
            );
        }
    }

    public function down(): void
    {
        if ($this->columnExists('quorum_polls', 'collection_position')) {
            \DBManager::get()->exec(
                'ALTER TABLE `quorum_polls` DROP COLUMN `collection_position`'
            );
        }
        // Drop index separately from the column — robust against aborted up()
        // runs that left a column without its index (or vice versa).
        if ($this->indexExists('quorum_polls', 'collection_id_idx')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP INDEX `collection_id_idx`');
        }
        if ($this->columnExists('quorum_polls', 'collection_id')) {
            \DBManager::get()->exec('ALTER TABLE `quorum_polls` DROP COLUMN `collection_id`');
        }
        \DBManager::get()->exec('DROP TABLE IF EXISTS `quorum_poll_collections`');
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
