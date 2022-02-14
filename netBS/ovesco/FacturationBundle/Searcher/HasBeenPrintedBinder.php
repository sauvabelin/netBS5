<?php

namespace Ovesco\FacturationBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\Type\HasBeenPrintedType;
use Symfony\Component\Form\Form;

class HasBeenPrintedBinder extends BaseBinder
{
    public function bindType()
    {
        return self::BIND;
    }

    public function getType()
    {
        return HasBeenPrintedType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        if (!in_array($form->getData(), ['yes', 'no'])) return;

        $em = $builder->getEntityManager();

        // THIS SHIT WAS SO ANNOYING I SPENT A WHOLE FUCKIN DAY ON IT
        // query looks like this
        /*
            select f.id, f.old_fichier_id from ovesco_facturation_factures f
            left join ovesco_facturation_rappels r on r.facture_id = f.id
            where r.id in (
                select r1.id from ovesco_facturation_factures f1
                join ovesco_facturation_rappels r1 on r1.facture_id = f1.id
                where r1.id = (
                    select r2.id from ovesco_facturation_rappels r2
                    where r2.facture_id = f1.id
                    order by r2.date desc
                    limit 1
                )
                and r1.date_impression is null
            )
            or (
                f.date_impression is null
                and f.id in (
                    select f5.id from ovesco_facturation_factures f5
                    left join ovesco_facturation_rappels r5 on r5.facture_id = f5.id
                    group by f5.id
                    having count(r5.id) = 0
                )
            )
         */
        $maxNested = $em->createQueryBuilder()->select('r2')->from("OvescoFacturationBundle:Rappel", 'r2')
            ->where('r2.facture = f1.id')
            ->orderBy('r2.date', 'desc');

        $subNested = $em->createQueryBuilder()->select('r1')->from('OvescoFacturationBundle:Facture', 'f1')
            ->leftJoin('f1.rappels', 'r1')
            ->where('r1.dateImpression IS NULL')
            ->andWhere("r1.id = (LIMIT({$maxNested->getDQL()}))");

        $or = $em->createQueryBuilder()->select('f5')->from('OvescoFacturationBundle:Facture', 'f5')
            ->leftJoin('f5.rappels', 'r5')
            ->groupBy('f5.id')
            ->having('COUNT(r5.id) = 0');

        $query = $em->createQueryBuilder()->select('DISTINCT f.id')->from('OvescoFacturationBundle:Facture', 'f')
            ->leftJoin('f.rappels', 'r')
            ->where($em->createQueryBuilder()->expr()->in('r.id', $subNested->getDQL()))
            ->orWhere($builder->expr()->andX(
                $builder->expr()->isNull('f.dateImpression'),
                $builder->expr()->in('f.id', $or->getDQL())
            ));

        $result = $query->getQuery()->getArrayResult();
        $waitingForPrintIds = array_map(function(array $item) { return $item['id']; }, $result);

        $adj = $form->getData() === 'yes' ? '' : 'NOT';
        $builder->andWhere("$alias.id $adj IN (:ids)")->setParameter('ids', $waitingForPrintIds);
    }
}
