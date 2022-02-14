<?php

namespace NetBS\FichierBundle\Model;

use NetBS\FichierBundle\Entity\Email;

class OwnableEmail extends Email
{
    protected $owner;

    public function __construct($owner, Email $email)
    {
        parent::__construct();

        $this->owner        = $owner;
        $this->email        = $email->getEmail();
        $this->remarques    = $email->getRemarques();
        $this->id           = $email->getId();
        $this->expediable   = $email->getExpediable();
    }

    public function getOwner()
    {
        return $this->owner;
    }
}