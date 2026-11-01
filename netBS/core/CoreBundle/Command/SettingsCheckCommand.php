<?php

namespace NetBS\CoreBundle\Command;

use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pre-deploy check: lists every important setting that is missing or broken and every
 * declared setting whose stored value is invalid. Exits non-zero if there is any.
 */
#[AsCommand(name: 'netbs:settings:check', description: 'Vérifie les paramètres système déclarés')]
class SettingsCheckCommand extends Command
{
    public function __construct(private ParameterManager $parameters)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $lines  = [];

        foreach ($this->parameters->problems() as $problem) {
            $definition = $problem->definition;
            $lines[]    = sprintf('%s (%s): %s', $definition->id(), $definition->label, $problem->message);
        }

        if ($lines === []) {
            $io->success('Tous les paramètres sont valides.');

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d problème(s) dans les paramètres système.', count($lines)));
        $io->listing($lines);

        return Command::FAILURE;
    }
}
