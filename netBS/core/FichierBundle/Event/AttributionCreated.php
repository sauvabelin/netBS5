<?php

namespace NetBS\FichierBundle\Event;

use NetBS\FichierBundle\Mapping\BaseAttribution;
use Symfony\Contracts\EventDispatcher\Event;

class AttributionCreated extends Event {

    private $attribution;

    public function __construct(BaseAttribution $attribution) {
        $this->attribution = $attribution;
    }

    public function getAttribution() {
        return $this->attribution;
    }
}