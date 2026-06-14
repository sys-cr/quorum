<?php

declare(strict_types=1);

/**
 * Stud.IP plugin CLI hook (see `studip-core/cli/studip` → `loadPluginCommands`).
 *
 * Referenced via the `cli=` key in the plugin manifest. Stud.IP loads this file
 * at CLI start, calls the returned closure and registers each command in the
 * Symfony console application.
 */
return static function (\Symfony\Component\Console\Application $application): array {
    // Plugin-owned Composer autoloader. Stud.IP's CLI loader requires our
    // command file directly without hooking in the plugin autoloader, so it
    // must be pulled here for `Quorum\Url\…` classes to resolve in the command.
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }

    return [
        \Quorum\Cli\MigrateShortUrlsCommand::class
            => __DIR__ . '/../lib/Cli/MigrateShortUrlsCommand.php',
        \Quorum\Cli\MigrateCliqrCommand::class
            => __DIR__ . '/../lib/Cli/MigrateCliqrCommand.php',
    ];
};
