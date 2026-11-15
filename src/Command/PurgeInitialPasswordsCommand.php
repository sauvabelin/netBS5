<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\LatestCreatedAccount;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Deletes every stored plaintext initial password (« comptes récents ») that is no longer the
 * account's password. Older accounts changed it through paths that didn't clean up (reset by
 * email, admin change). The « must change password » flag is left as is. Safe to re-run.
 */
#[AsCommand(
    name: 'netbs:accounts:purge-initial-passwords',
    description: 'Delete stored initial passwords that are no longer the account password.',
)]
final class PurgeInitialPasswordsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be deleted, delete nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $stale  = [];
        $kept   = 0;

        foreach ($this->em->getRepository(LatestCreatedAccount::class)->findAll() as $latest) {
            $user = $latest->getUser();
            if ($user !== null && $this->hasher->isPasswordValid($user, (string) $latest->getPassword())) {
                $kept++;
                continue;
            }

            $stale[] = $user?->getUsername() ?? sprintf('(no user, row %d)', $latest->getId());
            if (!$dryRun) {
                $this->em->remove($latest);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        if ($stale !== []) {
            $io->section(sprintf('%s (%d)', $dryRun ? 'Would delete' : 'Deleted', count($stale)));
            $io->listing($stale);
        }
        $io->success(sprintf('%d stale initial password(s) %s, %d still valid kept.', count($stale), $dryRun ? 'found' : 'deleted', $kept));

        return Command::SUCCESS;
    }
}
