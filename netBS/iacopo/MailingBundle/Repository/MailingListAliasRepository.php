<?php

namespace Iacopo\MailingBundle\Repository;

use Iacopo\MailingBundle\Entity\MailingListAlias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MailingListAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailingListAlias::class);
    }
}
