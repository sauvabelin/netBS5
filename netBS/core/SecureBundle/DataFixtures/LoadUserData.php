<?php

namespace NetBS\SecureBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class LoadUserData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $secureConfig;

    private $encoder;

    public function __construct(SecureConfig $config, UserPasswordEncoderInterface $encoder) {
        $this->secureConfig = $config;
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager): void
    {
        $userClass  = $this->secureConfig->getUserClass();

        $user       = new $userClass();
        $user->setUsername('admin');

        $encoder    = $this->encoder;
        $password   = $encoder->encodePassword($user, 'password');
        $user->setPassword($password);

        $user->addRole($this->getReference('ROLE_ADMIN'));

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