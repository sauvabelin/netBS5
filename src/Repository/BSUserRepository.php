<?php

namespace App\Repository;

use App\Entity\BSUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Users have two names: the permanent `username` (OIDC subject, LDAP uid,
 * token identifier — never typed by anyone) and the editable `loginUsername`
 * (displayed, and the only name accepted at sign-in). Both live in one
 * namespace — a string belongs to at most one user, whichever column it sits
 * in. {@see \App\Validator\UniqueLoginHandle} enforces it.
 */
class BSUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BSUser::class);
    }

    /** The user a typed login name belongs to (sign-in, password reset). */
    public function findOneByLoginUsername(string $loginUsername): ?BSUser
    {
        return $this->findOneBy(['loginUsername' => $loginUsername]);
    }

    /**
     * Whether any user other than $exceptId (soft-deleted ones included, they
     * still hold their unique index slots) uses one of $handles as username or
     * login username.
     *
     * @param string[] $handles
     */
    public function isHandleTaken(array $handles, ?int $exceptId = null): bool
    {
        $handles = array_values(array_unique(array_filter($handles, fn ($h) => $h !== null && $h !== '')));
        if ($handles === []) {
            return false;
        }

        $filters = $this->getEntityManager()->getFilters();
        $filterWasEnabled = $filters->isEnabled('softdeleteable');
        if ($filterWasEnabled) $filters->disable('softdeleteable');
        try {
            $qb = $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.username IN (:handles) OR u.loginUsername IN (:handles)')
                ->setParameter('handles', $handles);
            if ($exceptId !== null) {
                $qb->andWhere('u.id != :id')->setParameter('id', $exceptId);
            }
            return (int) $qb->getQuery()->getSingleScalarResult() > 0;
        } finally {
            if ($filterWasEnabled) $filters->enable('softdeleteable');
        }
    }
}
