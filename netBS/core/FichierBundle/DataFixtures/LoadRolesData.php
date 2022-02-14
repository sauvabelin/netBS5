<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Yaml\Yaml;

class LoadRolesData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    protected $secureConfig;

    public function __construct(SecureConfig $config) {
        $this->secureConfig = $config;
    }

    public function load(ObjectManager $manager)
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . "/../Resources/security/roles.yml"));
        $roles  = $this->loadRole($config['roles'], $manager);

        foreach($roles as $role) {

            $role->setParent($this->getReference('ROLE_ADMIN'));
            $manager->persist($role);
        }

        $manager->flush();
    }

    public function loadRole(array $data, ObjectManager $manager) {

        $roleClass  = $this->secureConfig->getRoleClass();
        $roles  = [];

        foreach($data as $name => $params) {

            $role   = new $roleClass($name, $params['poids'], $params['description']);

            if(isset($params['children'])) {

                $childs = $this->loadRole($params['children'], $manager);

                foreach($childs as $child)
                    $role->addChild($child);
            }

            $manager->persist($role);
            $roles[] = $role;
        }

        return $roles;
    }

    public static function getGroups(): array
    {
        return ['main', 'fill'];
    }

    public function getOrder()
    {
        return 300;
    }
}