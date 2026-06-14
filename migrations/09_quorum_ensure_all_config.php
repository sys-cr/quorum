<?php

declare(strict_types=1);

/**
 * Add missing `QUORUM_*` config keys for existing installs.
 *
 * `QUORUM_FREITEXT_BLOCKLIST` is created only in
 * `QuorumStudipPlugin::ensureMinRoleConfig()`. On already-active installs
 * `onEnable()` does not run after an update, so the key can be missing from
 * the system config and invisible to admins.
 *
 * `ensureMinRoleConfig()` is idempotent (creates each key only when missing)
 * and covers all keys, so a repeat call adds exactly the missing ones without
 * touching existing values.
 *
 * History: originally also created `QUORUM_AURORA_STYLE`; that toggle was
 * never evaluated anywhere and has been removed (cleanup: migration 10).
 */
final class QuorumEnsureAllConfig extends Migration
{
    public function description(): string
    {
        return 'Quorum: fehlenden QUORUM_FREITEXT_BLOCKLIST-Config-Key '
             . 'für Bestands-Installs nachziehen (idempotent).';
    }

    public function up(): void
    {
        if (class_exists(\QuorumStudipPlugin::class)) {
            \QuorumStudipPlugin::ensureMinRoleConfig();
            return;
        }

        // Fallback when the plugin bootstrap class is not autoloaded yet:
        // create the key directly with the same defaults and section.
        $config = \Config::get();
        if ($config->QUORUM_FREITEXT_BLOCKLIST === null) {
            $config->create('QUORUM_FREITEXT_BLOCKLIST', [
                'value'       => '',
                'type'        => 'string',
                'range'       => 'global',
                'section'     => 'quorum',
                'description' => 'Quorum: komma-getrennte Sperrbegriffe für anonyme '
                               . 'Freitext-Beiträge. Leer = Moderation aus.',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally do not delete the config keys — they may hold
        // admin-maintained values (blocklist). Rolling back this backfill
        // migration must not destroy settings.
    }
}
