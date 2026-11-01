<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Command;

use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectorySync;
use NetBS\AuthBundle\Directory\DirectorySyncRunner;
use NetBS\AuthBundle\Directory\LldapClientException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'netbs:ldap:sync',
    description: 'Make the lldap directory match netBS users and groups.',
)]
final class LdapSyncCommand extends Command
{
    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectorySync $sync,
        private readonly DirectorySyncRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the changes without applying them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            // A real run goes through the runner, so it never overlaps the worker's.
            $report = $dryRun ? $this->sync->sync($this->projection->snapshot(), true) : $this->runner->run();
        } catch (DirectoryNotConfiguredException|LldapClientException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $snapshot = $report->snapshot;
        $io->writeln(sprintf('netBS: %d users, %d groups, %d computed groups, %d role groups, %d frozen',
            count($snapshot->users), count($snapshot->groups), count($snapshot->computedGroups), count($snapshot->roleGroups), count($snapshot->frozen)));

        if ($report->changes !== []) {
            $io->listing($report->changes);
        }
        foreach ($report->errors as $error) {
            $io->warning($error);
        }
        // Frozen groups are left as they are in lldap until their rule is fixed. Groupes a broken
        // selection rule might pick, but which lldap doesn't have, are counted once per rule.
        foreach ($report->frozen as $frozen) {
            $io->warning(sprintf('gelé : %s — %s', $frozen->name, $frozen->reason));
        }
        foreach ($report->pending as $reason => $count) {
            $io->warning(sprintf('%d autre(s) groupe(s) en attente — %s', $count, $reason));
        }

        $io->success(sprintf('%s %d change(s), %d error(s).', $dryRun ? 'Would apply' : 'Applied', count($report->changes), count($report->errors)));
        return $report->errors === [] && $snapshot->frozen === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
