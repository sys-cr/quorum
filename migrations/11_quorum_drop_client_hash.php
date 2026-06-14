<?php

declare(strict_types=1);

/**
 * Remove `quorum_responses.client_hash` without replacement.
 *
 * Anonymous votes need no IP/client attribute: the value only served a weak
 * per-minute rate limit that locked legitimate participants out of each
 * other behind shared campus IPs (NAT) and did not stop attackers anyway.
 * Data minimization instead of a stored IP derivative; duplicate voting is
 * prevented client-side.
 *
 * Idempotent and DB-agnostic: column/index are only removed if they exist
 * (checked via `information_schema`, no `DROP ... IF EXISTS`, which not
 * every supported DB knows). The `poll_client` index references the column
 * and is removed first.
 */
final class QuorumDropClientHash extends Migration
{
    public function description(): string
    {
        return 'Quorum: client_hash-Spalte aus quorum_responses entfernen (Datenminimierung).';
    }

    public function up(): void
    {
        $db = \DBManager::get();

        if ($this->indexExists($db, 'poll_client')) {
            $db->exec('ALTER TABLE `quorum_responses` DROP INDEX `poll_client`');
        }
        if ($this->columnExists($db, 'client_hash')) {
            $db->exec('ALTER TABLE `quorum_responses` DROP COLUMN `client_hash`');
        }

        // Cleanup: remove a secret key possibly created by an earlier variant
        // (idempotent — usually not present at all).
        if (\Config::get()->QUORUM_RESPONSE_SALT !== null) {
            \Config::get()->delete('QUORUM_RESPONSE_SALT');
        }
    }

    public function down(): void
    {
        $db = \DBManager::get();

        if (!$this->columnExists($db, 'client_hash')) {
            $db->exec('ALTER TABLE `quorum_responses` ADD COLUMN `client_hash` varchar(64) DEFAULT NULL');
        }
        if (!$this->indexExists($db, 'poll_client')) {
            $db->exec('ALTER TABLE `quorum_responses` ADD INDEX `poll_client` (`poll_id`, `client_hash`, `mkdate`)');
        }
    }

    private function columnExists(\PDO $db, string $column): bool
    {
        return (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = 'quorum_responses'
                AND column_name = " . $db->quote($column)
        )->fetchColumn() > 0;
    }

    private function indexExists(\PDO $db, string $index): bool
    {
        return (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE()
                AND table_name = 'quorum_responses'
                AND index_name = " . $db->quote($index)
        )->fetchColumn() > 0;
    }
}
