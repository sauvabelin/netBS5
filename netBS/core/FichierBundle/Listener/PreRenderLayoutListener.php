<?php

namespace NetBS\FichierBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PreRenderLayoutListener
{
    protected $token;

    protected $stack;

    protected $manager;

    public function __construct(TokenStorageInterface $storage, RequestStack $stack, EntityManagerInterface $manager)
    {
        $this->token    = $storage;
        $this->stack    = $stack;
        $this->manager  = $manager;
    }

    public function preRender(PreRenderLayoutEvent $event) {

    }
}
