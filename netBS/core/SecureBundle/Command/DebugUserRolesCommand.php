<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\BSUser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'netbs:debug:user-roles')]
final class DebugUserRolesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $username]);

        if (!$user) {
            $output->writeln("<error>User '$username' not found.</error>");
            return Command::FAILURE;
        }

        $output->writeln("User: {$user->getUsername()} (id={$user->getId()})");
        $output->writeln("\nDirect roles:");
        foreach ($user->getDirectRoles() as $r) {
            $output->writeln("  - " . $r->getRole());
        }

        $output->writeln("\nAll roles (getAllRoles, via getChildrenRecursive):");
        foreach ($user->getAllRoles() as $r) {
            $output->writeln("  - " . $r->getRole());
        }

        $output->writeln("");
        foreach (['ROLE_ADMIN', 'ROLE_COMMANDANT', 'ROLE_SG', 'ROLE_READ_EVERYWHERE', 'ROLE_APMBS', 'ROLE_TRESORIER'] as $r) {
            $output->writeln(sprintf("hasRole %-25s : %s", $r, $user->hasRole($r) ? 'YES' : 'no'));
        }

        return Command::SUCCESS;
    }
}
