<?php

namespace NetBS\CoreBundle\Model;

use Doctrine\ORM\EntityManager;

abstract class BaseDeleter
{
    /**
     * @var EntityManager
     */
    protected $manager;

    abstract public function getManagedClass();

    abstract public function remove($id);

    public function setManager(EntityManager $manager) {
        $this->manager = $manager;
    }
}
