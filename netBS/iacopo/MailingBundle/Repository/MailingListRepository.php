<?php

namespace Iacopo\MailingBundle\Repository;

use Iacopo\MailingBundle\Entity\MailingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailingList::class);
    }

    /**
     * Find all mailing lists ordered by name
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a mailing list by base address
     */
    public function findByBaseAddress(string $baseAddress): ?MailingList
    {
        return $this->createQueryBuilder('m')
            ->where('m.baseAddress = :baseAddress')
            ->setParameter('baseAddress', $baseAddress)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
