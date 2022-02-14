<?php

namespace NetBS\CoreBundle\Event;

use Doctrine\ORM\EntityManager;
use NetBS\FichierBundle\Mapping\BaseFamille;
use Symfony\Contracts\EventDispatcher\Event;

class RemoveFamilleEvent extends Event
{
    const NAME = 'netbs.remove.famille';

    private $famille;

    private $manager;

    public function __construct(BaseFamille $famille, EntityManager $manager) {

        $this->famille = $famille;
        $this->manager = $manager;
    }

    public function getFamille()
    {
        return $this->famille;
    }

    /**
     * @return EntityManager
     */
    public function getManager()
    {
        return $this->manager;
    }
}
