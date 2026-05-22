<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'netbs:debug:user-roles')]
final class DebugUserRolesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecureConfig $secureConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');
        $userClass = $this->secureConfig->getUserClass();
        $repo = $this->em->getRepository($userClass);

        // Some app-level user classes (e.g. BSUser) add a `loginUsername`
        // alternative identifier. Probe via class metadata so the bundle
        // command works on installs that only expose `username`.
        $metadata = $this->em->getClassMetadata($userClass);

        $user = null;
        if ($metadata->hasField('loginUsername')) {
            $user = $repo->findOneBy(['loginUsername' => $username]);
        }
        $user ??= $repo->findOneBy(['username' => $username]);

        if (!$user instanceof BaseUser) {
            $output->writeln("<error>User '$username' not found.</error>");
            return Command::FAILURE;
        }

        $loginLabel = $metadata->hasField('loginUsername') && method_exists($user, 'getLoginUsername')
            ? $user->getLoginUsername()
            : $user->getUsername();
        $output->writeln("User: {$loginLabel} (id={$user->getId()})");
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
