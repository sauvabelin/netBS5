<?php

namespace NetBS\SecureBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Yaml\Yaml;

class LoadRolesData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $secureConfig;

    public function __construct(SecureConfig $config) {
        $this->secureConfig = $config;
    }

    public function load(ObjectManager $manager)
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . "/../Resources/security/system_roles.yml"));
        $roles  = $this->loadRole($config['roles'], $manager);

        foreach($roles as $role)
            $manager->persist($role);

        $manager->flush();

        $this->addReference('ROLE_ADMIN', $manager->getRepository('NetBSSecureBundle:Role')->findOneBy(array('role' => 'ROLE_ADMIN')));
    }

    public function loadRole(array $data, ObjectManager $manager) {

        $rc     = $this->secureConfig->getRoleClass();

        $roles  = [];

        foreach($data as $name => $params) {

            $role   = new $rc($name, $params['poids'], isset($params['description']) ? $params['description'] : '');

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
        return ['fill', 'main'];
    }

    public function getOrder()
    {
        return 1;
    }
}