<?php

namespace Iacopo\MailingBundle\Select2;

use Doctrine\ORM\EntityManagerInterface;
use Iacopo\MailingBundle\Entity\MailingList;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;

class MailingListSelect2Provider implements Select2ProviderInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getManagedClass(): string
    {
        return MailingList::class;
    }

    public function getClass(): string
    {
        return MailingList::class;
    }

    public function toString($entity): string
    {
        if (!$entity instanceof MailingList) {
            return '';
        }

        return $entity->getName() . ' (' . $entity->getBaseAddress() . ')';
    }

    public function toId($entity)
    {
        if (!$entity instanceof MailingList) {
            return null;
        }

        return $entity->getId();
    }

    public function search($needle, $limit = 5)
    {
        $qb = $this->entityManager
            ->getRepository(MailingList::class)
            ->createQueryBuilder('ml')
            ->orderBy('ml.name', 'ASC')
            ->setMaxResults($limit);

        if ($needle) {
            $qb->where('ml.name LIKE :query OR ml.baseAddress LIKE :query')
               ->setParameter('query', '%' . $needle . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
