<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;

/** @extends ServiceEntityRepository<DirectoryPublicationRule> */
class DirectoryPublicationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectoryPublicationRule::class);
    }

    /** @return list<DirectoryPublicationRule> in priority order */
    public function ordered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function maxPosition(): int
    {
        return (int) $this->createQueryBuilder('r')->select('MAX(r.position)')->getQuery()->getSingleScalarResult();
    }
}
