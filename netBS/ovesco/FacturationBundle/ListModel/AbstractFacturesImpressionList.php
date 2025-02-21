<?php

namespace Ovesco\FacturationBundle\ListModel;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\ListModel\AjaxModel;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Rappel;
use Ovesco\FacturationBundle\Util\FactureListTrait;

abstract class AbstractFacturesImpressionList extends AjaxModel
{
    use EntityManagerTrait, FactureListTrait;

    public function ajaxQueryBuilder(string $alias): QueryBuilder
    {
        $em = $this->entityManager;
        $builder = $em->getRepository(Facture::class)->createQueryBuilder($alias);
        $maxNested = $em->createQueryBuilder()->select('r2')->from(Rappel::class, 'r2')
            ->where('r2.facture = f1.id')
            ->orderBy('r2.date', 'desc');

        $subNested = $em->createQueryBuilder()->select('r1')->from(Facture::class, 'f1')
            ->leftJoin('f1.rappels', 'r1')
            ->where('r1.dateImpression IS NULL')
            ->andWhere("r1.id = (LIMIT({$maxNested->getDQL()}))");

        $or = $em->createQueryBuilder()->select('f5')->from(Facture::class, 'f5')
            ->leftJoin('f5.rappels', 'r5')
            ->groupBy('f5.id')
            ->having('COUNT(r5.id) = 0');

        $query = $em->createQueryBuilder()->select('DISTINCT f.id')->from(Facture::class, 'f')
            ->leftJoin('f.rappels', 'r')
            ->where($em->createQueryBuilder()->expr()->in('r.id', $subNested->getDQL()))
            ->orWhere($builder->expr()->andX(
                $builder->expr()->isNull('f.dateImpression'),
                $builder->expr()->in('f.id', $or->getDQL())
            ));

        $result = $query->getQuery()->getArrayResult();
        $waitingForPrintIds = array_map(function(array $item) { return $item['id']; }, $result);

        $adj = $this->hasBeenPrinted() ? '' : 'NOT';
        $builder->andWhere("$alias.id $adj IN (:ids)")->setParameter('ids', $waitingForPrintIds);
        $builder->andWhere("$alias.statut = :statut")
            ->setParameter('statut', Facture::OUVERTE);
        return $builder;
    }

    abstract protected function hasBeenPrinted(): bool;

    public function searchTerms(): array
    {
        return [];
    }
}