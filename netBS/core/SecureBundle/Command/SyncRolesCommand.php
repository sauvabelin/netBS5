<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Service\RoleTreeSyncer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reconcile the role tree in `netbs_secure_roles` with the YAML definitions
 * supplied by every bundle that tags a `RoleTreeSourceInterface` service.
 *
 * Safe to run on production: only the `netbs_secure_roles` table is touched.
 * No user, fonction, membre, autorisation, or other data is read or written.
 * Existing role rows are updated in place (poids / description / parent_id);
 * missing rows are inserted; orphan rows (in DB but not in any YAML) are
 * surfaced as a warning but never deleted automatically.
 */
#[AsCommand(
    name: 'netbs:roles:sync',
    description: 'Reconcile the role tree in the database with the YAML role definitions.',
)]
final class SyncRolesCommand extends Command
{
    public function __construct(
        private readonly RoleTreeSyncer $syncer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sync roles from YAML to database');

        $report = $this->syncer->syncAll();

        if ($report->dedupedFrom > 0) {
            $io->note(sprintf('Removed %d duplicate role row(s) before syncing.', $report->dedupedFrom));
        }

        if (!empty($report->created)) {
            $io->section(sprintf('Created (%d)', count($report->created)));
            $io->listing($report->created);
        }
        if (!empty($report->updated)) {
            $io->section(sprintf('Updated (%d)', count($report->updated)));
            $io->listing($report->updated);
        }
        $io->writeln(sprintf('Unchanged: %d', count($report->unchanged)));

        $touched = array_merge($report->created, $report->updated, $report->unchanged);
        $orphans = $this->detectOrphans($touched);
        if (!empty($orphans)) {
            $io->warning(
                'These roles exist in the database but are not in any YAML: '
                . implode(', ', $orphans)
                . '. They are left as-is. Add them to a YAML source or remove them manually if intended.'
            );
        }

        $io->success('Roles synced.');
        return Command::SUCCESS;
    }

    /**
     * @param string[] $touched Role names produced by the syncer this run.
     * @return string[] Role names in the DB that the syncer did not touch.
     */
    private function detectOrphans(array $touched): array
    {
        $allNames = array_column(
            $this->em->getConnection()->fetchAllAssociative('SELECT role FROM netbs_secure_roles'),
            'role'
        );
        $orphans = array_values(array_diff($allNames, $touched));
        sort($orphans);
        return $orphans;
    }
}
