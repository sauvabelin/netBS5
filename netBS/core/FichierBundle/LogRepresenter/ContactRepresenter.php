<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Service\ContactManager;

abstract class ContactRepresenter extends FichierRepresenter
{
    /**
     * @var ContactManager
     */
    protected $manager;

    public function __construct(ContactManager $manager)
    {
        $this->manager = $manager;
    }
}