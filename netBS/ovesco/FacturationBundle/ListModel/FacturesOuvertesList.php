<?php

namespace Ovesco\FacturationBundle\ListModel;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\ListModel\AjaxModel;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Util\FactureListTrait;

class FacturesOuvertesList extends AjaxModel
{
    use EntityManagerTrait, FactureListTrait;

    public function ajaxQueryBuilder(string $alias): QueryBuilder
    {
        $qb = $this->entityManager->getRepository(Facture::class)->createQueryBuilder($alias);
        return $qb
            ->andWhere("$alias.statut = :statut")
            ->setParameter('statut', Facture::OUVERTE);
    }

    public function searchTerms(): array
    {
        return ['remarques'];
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.factures_ouvertes';
    }
}
