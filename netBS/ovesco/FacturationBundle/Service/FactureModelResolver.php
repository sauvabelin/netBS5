<?php

namespace Ovesco\FacturationBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\InvalidRuleException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\FichierBundle\Mapping\BaseFamille;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/** Picks the facture model that applies to a facture: the first matching one by poids. */
class FactureModelResolver
{
    private ExpressionLanguage $language;

    public function __construct(
        private EntityManagerInterface $em,
        private RuleEvaluator $evaluator,
        private LoggerInterface $logger,
    ) {
        $this->language = new ExpressionLanguage();
    }

    public function resolve(Facture $facture): FactureModel
    {
        $models = $this->em->getRepository(FactureModel::class)
            ->createQueryBuilder('m')->orderBy('m.poids', 'DESC')->getQuery()->getResult();

        return $this->choose($models, $facture);
    }

    /**
     * @param FactureModel[] $models ordered by poids DESC
     * None matches => the highest-poids model.
     */
    public function choose(array $models, Facture $facture): FactureModel
    {
        if (empty($models)) {
            throw new \RuntimeException('Aucun modèle de facture trouvé en base de données');
        }

        foreach ($models as $model) {
            if ($this->applies($model, $facture)) {
                return $model;
            }
        }

        return $models[0];
    }

    private function applies(FactureModel $model, Facture $facture): bool
    {
        if ($model->getRule() !== null) {
            try {
                $rule = $model->getRule()->rule();
            } catch (InvalidRuleException $e) {
                $this->logger->warning('Facture model "{model}" has a malformed rule, counted as no match: {message}', [
                    'model'   => $model->getName(),
                    'message' => $e->getMessage(),
                ]);

                return false;
            }

            return $rule->isEmpty() || $this->evaluator->matches($rule, 'facture', $facture);
        }

        // Legacy expression, evaluated as before the rules existed; null = always applies.
        $expression = $model->getApplicationRule();
        if (trim((string) $expression) === '') {
            return true;
        }

        try {
            return (bool) $this->language->evaluate($expression, [
                'facture'   => $facture,
                'debiteur'  => $facture->getDebiteur(),
                'isFamille' => $facture->getDebiteur() instanceof BaseFamille,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Facture model "{model}" rule failed, counted as no match: {message}', [
                'model'   => $model->getName(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
