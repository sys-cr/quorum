<?php

declare(strict_types=1);

namespace Quorum\Cli;

use Quorum\Migration\CliqrMigrator;
use Quorum\Migration\CliqrSourceRepository;
use Quorum\Migration\MigrationLog;
use Quorum\Polls\CollectionsRepository;
use Quorum\Polls\PollsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php cli/studip quorum:migrate-cliqr [--dry-run]`
 *
 * Reads Cliqr voting assignments from the Stud.IP eTask schema
 * (`etask_assignments.type = 'cliqr:voting'`), maps them onto the Quorum
 * polls schema and writes them to `quorum_polls`. The original Cliqr data is
 * left untouched (quarantine by design).
 *
 * Idempotent — `quorum_migration_log` tracks already-migrated records.
 */
final class MigrateCliqrCommand extends Command
{
    protected static $defaultName        = 'quorum:migrate-cliqr';
    protected static $defaultDescription = 'Cliqr-Polls aus dem eTask-Schema in die Quorum-Tabellen migrieren.';

    protected function configure(): void
    {
        $this->setHelp(
            "Liest Cliqr-Voting-Assignments aus `etask_assignments`, mapped\n" .
            "auf `quorum_polls` und legt sie dort an. Mehrfaches Ausführen ist\n" .
            "sicher — `quorum_migration_log` verhindert Duplikate.\n" .
            "Original-Cliqr-Daten in `etask_*` werden NICHT verändert."
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Migration nur simulieren — kein Schreibzugriff. Fehler werden trotzdem gemeldet.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = new CliqrMigrator(
            source:      new CliqrSourceRepository(),
            target:      new PollsRepository(),
            log:         new MigrationLog(),
            collections: new CollectionsRepository(),
        );

        $votings    = $migrator->detect();
        $taskGroups = $migrator->detectTaskGroups();
        if ($votings === 0 && $taskGroups === 0) {
            $output->writeln('<info>Keine Cliqr-Daten (Votings/Sammlungen) gefunden — nichts zu migrieren.</info>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            'Detection: %d Voting-Assignment(s), %d Sammlung(en) im eTask-Schema.',
            $votings,
            $taskGroups,
        ));

        $dryRun = (bool) $input->getOption('dry-run');
        $report = $migrator->migrate(dryRun: $dryRun);

        $output->writeln(sprintf(
            '%sUmfragen: <info>%d</info>  Sammlungen: <info>%d</info>  ' .
            'Übersprungen: <comment>%d</comment>  Fehler: <error>%d</error>',
            $dryRun ? '[dry-run] ' : '',
            $report->migrated,
            $report->collectionsMigrated,
            count($report->skipped),
            count($report->errors),
        ));

        foreach ($report->skipped as $s) {
            $output->writeln(sprintf(
                '  • Skip: <comment>etask_assignment_id=%d</comment> — %s',
                $s['etask_assignment_id'],
                $s['reason'],
            ));
        }
        foreach ($report->errors as $e) {
            $output->writeln(sprintf(
                '  • Fehler: <error>etask_assignment_id=%d</error> — %s',
                $e['etask_assignment_id'],
                $e['error'],
            ));
        }

        return $report->errors === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
