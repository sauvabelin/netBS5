<?php

namespace NetBS\SecureBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\Entity\ExportConfiguration;
use NetBS\CoreBundle\Entity\Notification;
use NetBS\CoreBundle\Entity\UserLog;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Exceptions\UserCreationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    /**
     * @var UserPasswordHasherInterface
     */
    protected $encoder;

    /**
     * @var SecureConfig
     */
    protected $config;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    public function __construct(UserPasswordHasherInterface $encoder, SecureConfig $config, EntityManagerInterface $manager)
    {
        $this->encoder      = $encoder;
        $this->config       = $config;
        $this->em           = $manager;
    }

    /**
     * @param BaseMembre $membre
     * @return BaseUser|null
     */
    public function findMembreLinkedUser(BaseMembre $membre) {

        return $this->getUserRepo()->createQueryBuilder('user')
            ->where('user.membre = :membre')
            ->setParameter('membre', $membre)
            ->getQuery()
            ->execute();
    }

    public function encodePassword(BaseUser $user, $password) {

        return $this->encoder->hashPassword($user, $password);
    }

    public function find($id) {

        return $this->getUserRepo()->find($id);
    }

    public function createUser(BaseUser $user) {

        $this->checkUsernameAndEmail($user);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function updateUser(BaseUser $user) {

        $this->checkUsernameAndEmail($user);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function deleteUser(BaseUser $user) {

        $em             = $this->em;

        // Remove dynamics
        $dynamics = $em->getRepository(DynamicList::class)->findBy(['owner' => $user]);
        foreach($dynamics as $dynamic) $em->remove($dynamic);

        // Remove user export configurations
        $exportConfigs = $em->getRepository(ExportConfiguration::class)->findBy(['user' => $user]);
        foreach($exportConfigs as $e) $em->remove($e);

        // Remove notifications
        $notifications = $em->getRepository(Notification::class)->findBy(['user' => $user]);
        foreach($notifications as $n) $em->remove($n);

        // Remove user logs
        $logs = $em->getRepository(UserLog::class)->findBy(['user' => $user]);
        foreach($logs as $log) $em->remove($log);

        $em->flush();
        $em->remove($user);
        $em->flush();
    }

    public function buildUsername($username) {

        $i = 0;

        while($this->getUserRepo()->findOneBy(array('username' => $username)) !== null)
            $username = $username . $i++;

        return $username;
    }

    protected function usernameExists(BaseUser $user) {

        $result = $this->getUserRepo()->findOneBy(array('username' => $user->getUsername()));
        return $result !== null && $result->getId() !== $user->getId();
    }

    protected function emailExists(BaseUser $user) {

        if(empty($user->getEmail()))
            return false;

        $result = $this->getUserRepo()->findOneBy(array('email' => $user->getEmail()));
        return $result !== null && $result->getId() !== $user->getId();
    }

    protected function checkUsernameAndEmail(BaseUser $user) {

        if($this->emailExists($user))
            throw new UserCreationException("Email already taken");

        if($this->usernameExists($user))
            throw new UserCreationException("Username already taken");
    }

    protected function getUserRepo() {

        return $this->em->getRepository($this->config->getUserClass());
    }
}
