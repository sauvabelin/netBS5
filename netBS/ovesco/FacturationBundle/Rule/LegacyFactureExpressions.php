<?php

namespace Ovesco\FacturationBundle\Rule;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\LegacyRuleExpressions;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Form\FactureModelType;

/**
 * The application_rule expressions of models without a rule row, and the [expression] of their
 * old-format texts. The APMBS model is left out (maintained by another group).
 */
class LegacyFactureExpressions implements LegacyRuleExpressions
{
    const TEXTS = ['titre' => 'titre', 'topDescription' => 'texte du haut', 'bottomSalutations' => 'texte du bas'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function expressions(): array
    {
        $expressions = [];

        /** @var FactureModel $model */
        foreach ($this->em->getRepository(FactureModel::class)->findAll() as $model) {
            if ($model->getName() === FactureModelType::APMBS) {
                continue;
            }
            $label = sprintf('Modèle de facture « %s »', $model->getName());

            if ($model->getRule() === null && trim((string) $model->getApplicationRule()) !== '') {
                $expressions[] = ['label' => $label, 'context' => 'facture', 'expression' => $model->getApplicationRule()];
            }

            if ($model->getTextesFormat() === FactureModel::TEXTES_ANCIEN) {
                foreach (self::TEXTS as $field => $name) {
                    preg_match_all('/\[([^\]]*)\]/', (string) $model->{'get' . ucfirst($field)}(), $matches);
                    foreach ($matches[1] as $expression) {
                        $expressions[] = ['label' => "$label ($name)", 'context' => 'facture', 'expression' => $expression];
                    }
                }
            }
        }

        return $expressions;
    }
}
