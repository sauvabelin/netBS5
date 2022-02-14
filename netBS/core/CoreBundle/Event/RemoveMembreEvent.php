<?php

namespace NetBS\CoreBundle\Event;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Symfony\Contracts\EventDispatcher\Event;

class RemoveMembreEvent extends Event
{
    const NAME = 'netbs.remove.membre';

    private $membre;

    private $manager;

    public function __construct(BaseMembre $membre, EntityManagerInterface $manager) {

        $this->membre = $membre;
        $this->manager = $manager;
    }

    public function getMembre()
    {
        return $this->membre;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getManager()
    {
        return $this->manager;
    }
}
