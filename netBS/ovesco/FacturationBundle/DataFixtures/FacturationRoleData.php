<?php

namespace Ovesco\FacturationBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Service\SecureConfig;

class FacturationRoleData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    protected $config;

    public function __construct(SecureConfig $config) {
        $this->config = $config;
    }

    public function load(ObjectManager $manager)
    {
        $role = $this->config->createRole();
        $role->setRole('ROLE_TRESORIER');
        $role->setDescription('Donne accès illimités à la section facturation');
        $role->setPoids(1000);
        $manager->persist($role);
        $manager->flush();
    }

    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder()
    {
        return 10;
    }

    public static function getGroups(): array
    {
        return ['main', 'fill', 'facturation'];
    }
}
