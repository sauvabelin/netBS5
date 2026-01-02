<?php

namespace Iacopo\MailingBundle\Repository;

use Iacopo\MailingBundle\Entity\MailingTarget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailingTargetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailingTarget::class);
    }

    /**
     * Find all targets for a mailing list
     */
    public function findByMailingList(int $mailingListId): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.mailingList = :listId')
            ->setParameter('listId', $mailingListId)
            ->orderBy('t.type', 'ASC')
            ->addOrderBy('t.targetEmail', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
