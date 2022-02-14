<?php

namespace NetBS\FichierBundle\Model;

use NetBS\FichierBundle\Entity\Telephone;

class OwnableTelephone extends Telephone
{
    protected $owner;

    public function __construct($owner, Telephone $telephone)
    {
        parent::__construct();

        $this->owner        = $owner;
        $this->telephone    = $telephone->getTelephone();
        $this->remarques    = $telephone->getRemarques();
        $this->id           = $telephone->getId();
        $this->expediable   = $telephone->getExpediable();
    }

    public function getOwner()
    {
        return $this->owner;
    }
}