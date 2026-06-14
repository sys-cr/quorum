<?php

declare(strict_types=1);

/**
 * Standalone wrapper for `Quorum\Migration\CliqrMigrator` — works in BOTH
 * setups:
 *
 *   - Stud.IP 6.1+:  better via `php cli/studip quorum:migrate-cliqr`
 *                   (the Stud.IP plugin CLI hook is only available there)
 *   - Stud.IP 6.0:   `php /var/www/studip/public/plugins_packages/studip-quorum/QuorumStudipPlugin/scripts/migrate-cliqr.php`
 *                   — the CLI hook is missing; this wrapper boots the Stud.IP
 *                   CLI env manually and calls the same migrator class.
 *
 * Options:
 *   --dry-run   Write nothing, only show what would be migrated.
 *
 * Exit code: 0 = success, 1 = errors in the report.
 */

// Path detection: this wrapper lives at {plugin_root}/scripts/migrate-cliqr.php
// and needs (a) the plugin Composer autoloader, (b) Stud.IP's bootstrap.
$pluginRoot = realpath(__DIR__ . '/..');
$studipRoot = realpath(__DIR__ . '/../../../../..');

if ($pluginRoot === false || !is_file($pluginRoot . '/QuorumStudipPlugin.php')) {
    fwrite(STDERR, "✗ Quorum-Plugin-Root nicht erkannt — Skript muss aus dem Plugin-Verzeichnis aufgerufen werden.\n");
    exit(2);
}
if ($studipRoot === false || !is_file($studipRoot . '/cli/studip_cli_env.inc.php')) {
    fwrite(STDERR, "✗ Stud.IP-CLI-Env nicht gefunden ({$studipRoot}/cli/studip_cli_env.inc.php).\n");
    exit(2);
}

require $studipRoot . '/cli/studip_cli_env.inc.php';
require $studipRoot . '/composer/autoload.php';
require $pluginRoot . '/vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv, true);

$migrator = new \Quorum\Migration\CliqrMigrator(
    source:      new \Quorum\Migration\CliqrSourceRepository(),
    target:      new \Quorum\Polls\PollsRepository(),
    log:         new \Quorum\Migration\MigrationLog(),
    collections: new \Quorum\Polls\CollectionsRepository(),
);

$votings    = $migrator->detect();
$taskGroups = $migrator->detectTaskGroups();
if ($votings === 0 && $taskGroups === 0) {
    echo "Keine Cliqr-Daten (Votings/Sammlungen) gefunden — nichts zu migrieren.\n";
    exit(0);
}

echo sprintf(
    "Detection: %d Voting-Assignment(s), %d Sammlung(en) im eTask-Schema.\n",
    $votings,
    $taskGroups,
);

$report = $migrator->migrate(dryRun: $dryRun);

echo sprintf(
    "%sUmfragen: %d  Sammlungen: %d  Übersprungen: %d  Fehler: %d\n",
    $dryRun ? '[dry-run] ' : '',
    $report->migrated,
    $report->collectionsMigrated,
    count($report->skipped),
    count($report->errors),
);

foreach ($report->skipped as $s) {
    echo sprintf("  • Skip: etask_assignment_id=%d — %s\n", $s['etask_assignment_id'], $s['reason']);
}
foreach ($report->errors as $e) {
    fwrite(STDERR, sprintf("  • Fehler: etask_assignment_id=%d — %s\n", $e['etask_assignment_id'], $e['error']));
}

exit($report->errors === [] ? 0 : 1);
