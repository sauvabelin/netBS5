<?php

namespace App\Repository;

use App\Entity\BSUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Users have two names: the permanent `username` (OIDC subject, token
 * identifier) and the editable `loginUsername`. Both live in one namespace —
 * a string belongs to at most one user, whichever column it sits in — so a
 * handle typed at login and an identifier carried by a token can never point
 * at two different users. {@see \App\Validator\UniqueLoginHandle} enforces it.
 */
class BSUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BSUser::class);
    }

    /**
     * Resolves a login handle or a permanent username to its user. The
     * permanent username wins, so tokens (JWT, remember-me) always reload the
     * user they were issued to.
     */
    public function findOneByHandle(string $handle): ?BSUser
    {
        return $this->findOneBy(['username' => $handle])
            ?? $this->findOneBy(['loginUsername' => $handle]);
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
