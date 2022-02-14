<?php

namespace NetBS\CoreBundle\Service;


use Doctrine\ORM\EntityManager;
use NetBS\CoreBundle\Entity\Notification;
use NetBS\SecureBundle\Mapping\BaseUser;
use Psr\Log\LoggerInterface;

class Notifier
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(EntityManager $entityManager, LoggerInterface $logger) {

        $this->entityManager    = $entityManager;
        $this->logger           = $logger;
    }

    public function notify(BaseUser $user, $message, $route = null) {

        $notification   = new Notification($user, $message, $route);
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function log() {

        return $this->logger;
    }
}