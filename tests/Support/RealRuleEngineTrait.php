<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\FichierBundle\Rule\AttributionSousGroupeContext;
use NetBS\FichierBundle\Rule\Block;
use NetBS\FichierBundle\Rule\MembreContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/** A RuleEvaluator over the real Fichier contexts and blocks, without the container. */
trait RealRuleEngineTrait
{
    protected function fichierRuleEvaluator(?LoggerInterface $logger = null): RuleEvaluator
    {
        $registry = new RuleRegistry(
            [new MembreContext(), new AttributionSousGroupeContext()],
            [
                new Block\MembreAttributionGroupesBlock(), new Block\MembreFonctionPoidsBlock(),
                new Block\MembreFonctionsBlock(), new Block\MembreGroupeTypesBlock(),
                new Block\AttributionFonctionsBlock(), new Block\AttributionFonctionPoidsBlock(),
                new Block\AttributionGroupeTypesBlock(), new Block\AttributionProfondeurBlock(),
            ],
        );

        return new RuleEvaluator($registry, $logger ?? new NullLogger());
    }
}
