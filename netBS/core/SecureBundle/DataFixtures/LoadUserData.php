<?php

namespace NetBS\SecureBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoadUserData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $secureConfig;

    private $hasher;

    public function __construct(SecureConfig $config, UserPasswordHasherInterface $hasher) {
        $this->secureConfig = $config;
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $userClass  = $this->secureConfig->getUserClass();

        $user       = new $userClass();
        $user->setUsername($_ENV['NETBS_ADMIN_USERNAME'] ?? 'admin');

        $plainPassword = $_ENV['NETBS_ADMIN_PASSWORD'] ?? 'password';
        $password   = $this->hasher->hashPassword($user, $plainPassword);
        $user->setPassword($password);

        $user->addRole($this->getReference('ROLE_ADMIN', Role::class));

        $manager->persist($user);
        $manager->flush();

        $this->addReference('admin', $user);
    }

    public static function getGroups(): array
    {
        return ['fill', 'main'];
    }

    public function getOrder(): int
    {
        return 100;
    }
}
