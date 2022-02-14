<?php

namespace NetBS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\SecureBundle\Mapping\BaseUser;

class DynamicListRepository extends EntityRepository
{
    /**
     * @param BaseUser $user
     * @return DynamicList[]
     */
    public function findForUser(BaseUser $user)
    {
        return $this->createQueryBuilder('d')
            ->where('d.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}