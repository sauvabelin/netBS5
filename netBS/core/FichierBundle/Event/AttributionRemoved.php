<?php

namespace NetBS\FichierBundle\Event;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Symfony\Contracts\EventDispatcher\Event;

class AttributionRemoved extends Event {

    private $membre;

    private $groupe;

    public function __construct(BaseMembre $membre, BaseGroupe $groupe) {
        $this->membre = $membre;
        $this->groupe = $groupe;
    }

    public function getMembre() {
        return $this->membre;
    }

    public function getGroupe() {
        return $this->groupe;
    }
}