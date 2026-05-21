<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'netbs:debug:role-tree',
    description: 'Print the synced role hierarchy as a tree, with descriptions and poids.'
)]
final class DebugRoleTreeCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('no-descriptions', null, InputOption::VALUE_NONE, 'Hide descriptions (compact output).')
            ->addOption('no-poids', null, InputOption::VALUE_NONE, 'Hide poids column.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Role[] $all */
        $all = $this->em->getRepository(Role::class)->findAll();

        if ($all === []) {
            $output->writeln('<comment>No roles in the database. Run `bin/console roles:sync` first.</comment>');
            return Command::SUCCESS;
        }

        $roots = array_values(array_filter($all, static fn (Role $r) => $r->getParent() === null));
        usort($roots, static fn (Role $a, Role $b) => $b->getPoids() <=> $a->getPoids());

        $showDesc = !$input->getOption('no-descriptions');
        $showPoids = !$input->getOption('no-poids');

        $reachable = [];
        foreach ($roots as $root) {
            $this->renderNode($output, $root, '', true, $reachable, $showDesc, $showPoids);
        }

        $orphans = array_filter($all, static fn (Role $r) => !isset($reachable[spl_object_id($r)]));
        if ($orphans !== []) {
            $output->writeln('');
            $output->writeln('<comment>Unreachable roles (cycle or detached parent):</comment>');
            foreach ($orphans as $orphan) {
                $output->writeln(sprintf('  - %s', $orphan->getRole()));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%d role(s) total, %d root(s).</info>', count($all), count($roots)));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, true> $reachable Set of spl_object_id -> true, mutated to detect orphans.
     */
    private function renderNode(
        OutputInterface $output,
        Role $node,
        string $prefix,
        bool $isLast,
        array &$reachable,
        bool $showDesc,
        bool $showPoids,
    ): void {
        $reachable[spl_object_id($node)] = true;

        $connector = $prefix === '' ? '' : ($isLast ? '└── ' : '├── ');
        $line = $prefix . $connector . '<info>' . $node->getRole() . '</info>';

        if ($showPoids) {
            $line .= sprintf(' <fg=gray>[%d]</>', $node->getPoids());
        }
        if ($showDesc) {
            $desc = trim((string) $node->getDescription());
            $line .= $desc === ''
                ? ' <fg=gray>—</>'
                : ' <fg=gray>'.$desc.'</>';
        }
        $output->writeln($line);

        $children = $node->getChildren()->toArray();
        usort($children, static fn (Role $a, Role $b) => $b->getPoids() <=> $a->getPoids());

        $childPrefix = $prefix === '' ? '' : $prefix . ($isLast ? '    ' : '│   ');
        $count = count($children);
        foreach ($children as $i => $child) {
            $this->renderNode(
                $output,
                $child,
                $childPrefix,
                $i === $count - 1,
                $reachable,
                $showDesc,
                $showPoids,
            );
        }
    }
}
