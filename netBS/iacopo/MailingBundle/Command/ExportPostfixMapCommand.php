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

#[AsCommand(
    name: 'netbs:mailing:export-postfix',
    description: 'Export the mailing lists as a Postfix virtual alias map (input for postmap lmdb:).',
)]
final class ExportPostfixMapCommand extends Command
{
    public function __construct(
        private readonly PostfixVirtualMapBuilder $builder,
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

        // Write next to the target and rename, so readers never see a partial file.
        $tmp = @tempnam(\dirname($path), '.postfix-map-');
        if ($tmp === false || file_put_contents($tmp, $map->render()) === false || !chmod($tmp, 0644) || !rename($tmp, $path)) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            $io->error(sprintf('Could not write %s', $path));
            return Command::FAILURE;
        }

        $io->success(sprintf('Wrote %d addresses to %s', count($map->entries), $path));
        return Command::SUCCESS;
    }
}
