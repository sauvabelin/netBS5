<?php

namespace NetBS\CoreBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Pre-deploy check: every stored rule (structure, references, expert syntax), orphan rows, and the
 * legacy expressions still in use. Exits non-zero if there is any problem.
 */
#[AsCommand(name: 'netbs:rules:check', description: 'Vérifie les règles et les anciennes expressions')]
class RulesCheckCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RuleValidator $validator,
        #[TaggedIterator('netbs.rule_owner_labeler')] private iterable $labelers,
        #[TaggedIterator('netbs.legacy_rule_expressions')] private iterable $legacy,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $lines = [];

        foreach ($this->em->getRepository(StoredRule::class)->findBy([], ['id' => 'ASC']) as $rule) {
            $label = $this->label($rule);
            if ($label === null) {
                $lines[] = sprintf('Règle orpheline #%d (%s)', $rule->getId(), $rule->getContext());
                continue;
            }
            foreach ($this->validator->validate($rule->getDefinition(), $rule->getContext()) as $message) {
                $lines[] = "$label : $message";
            }
        }

        foreach ($this->legacy as $source) {
            foreach ($source->expressions() as ['label' => $label, 'context' => $context, 'expression' => $expression]) {
                foreach ($this->validator->validate(Rule::expert($expression), $context) as $message) {
                    $lines[] = "$label : $message";
                }
            }
        }

        if ($lines === []) {
            $io->success('Toutes les règles sont valides.');

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d problème(s) dans les règles.', count($lines)));
        $io->listing($lines);

        return Command::FAILURE;
    }

    private function label(StoredRule $rule): ?string
    {
        foreach ($this->labelers as $labeler) {
            if (($label = $labeler->label($rule)) !== null) {
                return $label;
            }
        }

        return null;
    }
}
