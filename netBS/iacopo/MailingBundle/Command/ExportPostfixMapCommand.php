<?php

declare(strict_types=1);

namespace Iacopo\MailingBundle\Command;

use Iacopo\MailingBundle\Service\PostfixVirtualMapBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'netbs:mailing:export-postfix',
    description: 'Export the mailing lists as a Postfix virtual alias map (input for postmap lmdb:).',
)]
final class ExportPostfixMapCommand extends Command
{
    public function __construct(
        private readonly PostfixVirtualMapBuilder $builder,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File to write, replaced atomically; "-" for stdout', '-')
            ->addOption('allow-empty', null, InputOption::VALUE_NONE, 'Write the map even if it has no entries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Diagnostics go to stderr so stdout stays a clean map when piping.
        $io = (new SymfonyStyle($input, $output))->getErrorStyle();

        $map = $this->builder->build();
        foreach ($map->warnings as $warning) {
            $io->warning($warning);
        }

        // An empty map almost always means a broken DB or query, and installing
        // it would make Postfix reject mail for every list.
        if ($map->entries === [] && !$input->getOption('allow-empty')) {
            $io->error('The map is empty; refusing to write it. Pass --allow-empty if this is intended.');
            return Command::FAILURE;
        }

        $path = (string) $input->getOption('output');
        if ($path === '-') {
            $output->write($map->render(), false, OutputInterface::OUTPUT_RAW);
            return Command::SUCCESS;
        }

        // dumpFile writes a temp file and renames it, so readers never see a partial map.
        try {
            $this->filesystem->dumpFile($path, $map->render());
            $this->filesystem->chmod($path, 0644);
        } catch (IOException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote %d addresses to %s', count($map->entries), $path));
        return Command::SUCCESS;
    }
}
