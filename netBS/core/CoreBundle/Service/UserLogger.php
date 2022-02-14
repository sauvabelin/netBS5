<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManager;
use NetBS\CoreBundle\Entity\UserLog;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;

class UserLogger
{
    const INFO      = "info";
    const WARNING   = "warning";
    const DANGER    = "danger";
    const SUCCESS   = "success";

    private $manager;

    private $config;

    public function __construct(EntityManager $manager, SecureConfig $config)
    {
        $this->manager  = $manager;
        $this->config   = $config;
    }

    public function logUsername($username, $level, $message) {

        $repo   = $this->manager->getRepository($this->config->getUserClass());
        $user   = $repo->findOneBy(array('username' => $username));

        if(!$user)
            $this->log($repo->findOneBy(array('username' => 'admin')), self::DANGER,
                "A log was adressed to $username but this user doesn't exist: <<$message>>");
        else
            $this->log($user, $level, $message);
    }

    /**
     * @param BaseUser $user
     * @param $level
     * @param $message
     */
    public function log($user, $level, $message) {

        $log    = new UserLog();
        $log->setUser($user)
            ->setLevel($level)
            ->setMessage($message);

        $this->manager->persist($log);
        $this->manager->flush();
    }
}