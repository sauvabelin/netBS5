<?php

namespace NetBS\CoreBundle\Utils\Traits;

use Doctrine\ORM\EntityManager;

trait EntityManagerTrait
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function setEntityManager(EntityManager $manager) {

        $this->entityManager    = $manager;
    }
}
