<?php

declare(strict_types=1);

namespace Quorum\Cli;

use Quorum\Url\NativeShortUrlAdapter;
use Quorum\Url\QuorumShortUrlAdapter;
use Quorum\Url\Migrator\ShortUrlMigrator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/studip quorum:migrate-short-urls [--dry-run]`
 *
 * Copies poll short links from the Quorum fallback table (`quorum_short_urls`)
 * into the Stud.IP-native table `short_urls`. Idempotent. Conflicts (alias
 * already taken) are listed in the report; the Quorum entry is then kept and
 * still resolved by the CompositeAdapter.
 *
 * Requires Stud.IP 6.2 (or a backport — the `short_urls` table must exist).
 */
final class MigrateShortUrlsCommand extends Command
{
    protected static $defaultName        = 'quorum:migrate-short-urls';
    protected static $defaultDescription = 'Quorum-Kurzlinks in die Stud.IP-`short_urls`-Tabelle migrieren.';

    protected function configure(): void
    {
        $this->setHelp(
            "Kopiert Einträge aus der Quorum-Fallback-Tabelle in die Stud.IP-native\n" .
            "`short_urls`-Tabelle. Mehrfaches Ausführen ist sicher — bereits migrierte\n" .
            "Einträge sind aus der Quorum-Tabelle entfernt und kommen nicht erneut.\n" .
            "Konflikte (Alias schon vergeben) bleiben in der Quorum-Tabelle und werden\n" .
            "vom CompositeAdapter weiterhin aufgelöst — der Polls-Flow bricht nie."
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Migration nur simulieren — kein Schreibzugriff. Konflikte werden trotzdem gemeldet.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        if (!self::tableExists('short_urls')) {
            $output->writeln(
                '<error>Stud.IPs `short_urls`-Tabelle existiert nicht.' .
                ' Migration setzt Stud.IP 6.2+ voraus.</error>'
            );
            return Command::FAILURE;
        }
        if (!self::tableExists('quorum_short_urls')) {
            $output->writeln(
                '<info>Quorum-Fallback-Tabelle `quorum_short_urls` existiert nicht — nichts zu migrieren.</info>'
            );
            return Command::SUCCESS;
        }

        $migrator = new ShortUrlMigrator(
            native: new NativeShortUrlAdapter(),
            quorum: new QuorumShortUrlAdapter(),
        );
        $report = $migrator->migrate(dryRun: $dryRun);

        $output->writeln(sprintf(
            '%sMigriert: <info>%d</info>  Konflikte: <comment>%d</comment>  Fehler: <error>%d</error>',
            $dryRun ? '[dry-run] ' : '',
            $report->migrated,
            count($report->conflicts),
            count($report->errors),
        ));

        foreach ($report->conflicts as $c) {
            $output->writeln(sprintf('  • Konflikt: <comment>%s</comment> — %s', $c['alias'], $c['reason']));
        }
        foreach ($report->errors as $e) {
            $output->writeln(sprintf('  • Fehler:   <error>%s</error> — %s', $e['alias'], $e['error']));
        }

        return $report->errors === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private static function tableExists(string $table): bool
    {
        $stmt = \DBManager::get()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }
}
