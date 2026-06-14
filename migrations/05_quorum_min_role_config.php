<?php

declare(strict_types=1);

/**
 * Register the `QUORUM_MIN_ROLE` system config so admins can manage the
 * workplace tile.
 *
 * Fresh installs get the value from `QuorumStudipPlugin::onEnable()`. For
 * installs that were already active, `onEnable()` does not run again, so this
 * migration covers them.
 *
 * Idempotent: creates the value only when missing. Rollback removes it.
 *
 * Section `quorum` makes it appear as its own section in Stud.IP's system
 * config rather than an anonymous entry in `global`.
 */
final class QuorumMinRoleConfig extends Migration
{
    public function description(): string
    {
        return 'Quorum: QUORUM_MIN_ROLE in der Stud.IP-Systemkonfiguration anlegen.';
    }

    public function up(): void
    {
        if (class_exists(\QuorumStudipPlugin::class)) {
            \QuorumStudipPlugin::ensureMinRoleConfig();
            return;
        }

        // Fallback when the plugin bootstrap class is not autoloaded yet:
        // insert directly with the same default and section.
        if (\Config::get()->QUORUM_MIN_ROLE === null) {
            \Config::get()->create('QUORUM_MIN_ROLE', [
                'value'       => 'dozent',
                'type'        => 'string',
                'range'       => 'global',
                'section'     => 'quorum',
                'description' => 'Quorum: ab welcher Stud.IP-Systemrolle die Arbeitsplatz-Kachel '
                               . 'sichtbar wird (user, autor, tutor, dozent, admin, root). Default: dozent.',
            ]);
        }
    }

    public function down(): void
    {
        \Config::get()->delete('QUORUM_MIN_ROLE');
    }
}
