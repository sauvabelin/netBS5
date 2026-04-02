<?php

namespace NetBS\SecureBundle\Service;

use Doctrine\ORM\EntityManager;
use NetBS\SecureBundle\Exceptions\UserCreationException;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Model\BaseUserProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class NetBSUserProvider extends BaseUserProvider
{
    protected $manager;

    protected $config;

    protected $hasher;

    public function __construct(EntityManager $manager, SecureConfig $config, UserPasswordHasherInterface $hasher)
    {
        $this->manager  = $manager;
        $this->config   = $config;
        $this->hasher   = $hasher;
    }

    public function loadUserByUsername($username)
    {
        $user   = $this->manager->getRepository($this->config->getUserClass())
            ->findOneBy(array('username' => $username));

        if(!$user)
            throw new UsernameNotFoundException();

        return $user;
    }

    public function createUser(BaseUser $user, $encodePassword = true) {

        $this->checkUsernameAndEmail($user);

        if($encodePassword)
            $user->setPassword($this->hasher->hashPassword($user, $user->getPassword()));

        $this->manager->persist($user);
        $this->manager->flush();
    }

    public function updateUser(BaseUser $user, $encodePassword = true) {

        $this->checkUsernameAndEmail($user);

        if($encodePassword)
            $user->setPassword($this->hasher->hashPassword($user, $user->getPassword()));

        $this->manager->persist($user);
        $this->manager->flush();
    }

    public function deleteUser(BaseUser $user) {

        $this->manager->remove($user);
        $this->manager->flush();
    }

    public function refresh(BaseUser $user)
    {
        $class = $this->config->getUserClass();
        if(!$user instanceof $class)
            throw new UnsupportedUserException();

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $this->config->getUserClass();
    }

    protected function usernameTaken(BaseUser $user) {

        $result = $this->getUserRepo()->findOneBy(array('username' => $user->getUsername()));
        return $result !== null && $result->getId() !== $user->getId();
    }

    protected function emailTaken(BaseUser $user) {

        if(empty($email))
            return false;

        $result = $this->getUserRepo()->findOneBy(array('email' => $user->getEmail()));
        return $result !== null && $result->getId() !== $user->getId();
    }

    protected function checkUsernameAndEmail(BaseUser $user) {

        if($this->emailTaken($user))
            throw new UserCreationException("Email already taken");

        if($this->usernameTaken($user))
            throw new UserCreationException("Username already taken");
    }

    protected function getUserRepo() {

        return $this->manager->getRepository($this->config->getUserClass());
    }
}
