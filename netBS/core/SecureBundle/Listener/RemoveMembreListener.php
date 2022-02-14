<?php

namespace NetBS\SecureBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Event\RemoveMembreEvent;
use NetBS\SecureBundle\Service\SecureConfig;
use NetBS\SecureBundle\Service\UserManager;

class RemoveMembreListener
{
    private $config;

    private $userManager;

    public function __construct(SecureConfig $secureConfig, UserManager $manager)
    {
        $this->config = $secureConfig;
        $this->userManager = $manager;
    }

    public function onRemove(RemoveMembreEvent $event) {
        $membre = $event->getMembre();
        $manager = $event->getManager();
        $user = $manager->getRepository($this->config->getUserClass())->findOneBy(['membre' => $membre]);
        if ($user) {
            $this->userManager->deleteUser($user);
        }
    }
}
