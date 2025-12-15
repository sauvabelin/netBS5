<?php

namespace Iacopo\MailingBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Service\SecureConfig;


// Shit does not work. Add manually to database: INSERT INTO netbs_secure_roles (role, poids, description, parent_id) VALUES ('ROLE_MAILING', 980, 'Gestionnaire des listes de diffusion', 2);

class MailingRoleData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    protected $config;

    public function __construct(SecureConfig $config) {
        $this->config = $config;
    }

    public function load(ObjectManager $manager): void
    {
        $role = $this->config->createRole();
        $role->setRole('ROLE_MAILING');
        $role->setDescription('Donne accès illimités aux listes de diffusion');
        $role->setPoids(1000);
        $manager->persist($role);
        $manager->flush();
    }

    public function getOrder(): int
    {
        return 10;
    }

    public static function getGroups(): array
    {
        return ['main', 'fill', 'mailing'];
    }
}
