<?php

declare(strict_types=1);

/**
 * Course assignment for collections: a collection can (optionally) be assigned
 * to a course — INDEPENDENT of the `seminar_id` values of its member polls
 * (those keep their own binding). This lets a collection appear in the Quorum
 * course tab without its questions having to belong to the same course.
 * Existing collections stay course-independent (NULL).
 *
 * Idempotent via `information_schema` (helpers typed as `\PDO $db` —
 * `DBManager::get()` returns a StudipPDO, not a DBManager).
 */
final class QuorumCollectionSeminar extends Migration
{
    public function description(): string
    {
        return 'Quorum: Kurszuordnung für Sammlungen (seminar_id auf quorum_poll_collections, optional).';
    }

    public function up(): void
    {
        $db = \DBManager::get();

        if (!$this->columnExists($db, 'quorum_poll_collections', 'seminar_id')) {
            $db->exec(
                "ALTER TABLE `quorum_poll_collections`
                    ADD COLUMN `seminar_id` varchar(32) NULL DEFAULT NULL AFTER `user_id`"
            );
        }
        if (!$this->indexExists($db, 'quorum_poll_collections', 'seminar_id_idx')) {
            $db->exec('ALTER TABLE `quorum_poll_collections` ADD KEY `seminar_id_idx` (`seminar_id`)');
        }
    }

    public function down(): void
    {
        $db = \DBManager::get();

        if ($this->indexExists($db, 'quorum_poll_collections', 'seminar_id_idx')) {
            $db->exec('ALTER TABLE `quorum_poll_collections` DROP INDEX `seminar_id_idx`');
        }
        if ($this->columnExists($db, 'quorum_poll_collections', 'seminar_id')) {
            $db->exec('ALTER TABLE `quorum_poll_collections` DROP COLUMN `seminar_id`');
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
