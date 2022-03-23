<?php

namespace NetBS\CoreBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\CoreBundle\Entity\Parameter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

class LoadParametersData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    protected $kernel;

    public function __construct(KernelInterface $kernel) {
        $this->kernel = $kernel;
    }

    public function load(ObjectManager $manager)
    {
        $bundles = $this->kernel->getBundles();
        $paths = [];
        foreach ($bundles as $bundle) {
            $paths[] = $bundle->getPath() . "/Resources/config/parameters.yml";
        }
        $paths[] = __DIR__ . "/../../../../src/Resources/config/parameters.yaml";

        foreach($paths as $path) {

            if (!file_exists($path)) {
                echo "No file at path " . $path;
                continue;
            }

            $params = Yaml::parse(file_get_contents($path));

            foreach($params as $namespace => $parameters) {

                foreach ($parameters as $key => $value) {

                    $param = $manager->getRepository('NetBSCoreBundle:Parameter')->findOneBy(array(
                        'namespace' => $namespace,
                        'paramKey'  => $key
                    ));

                    if(!$param)
                        $param = new Parameter($namespace, $key, $value);
                    else
                        $param->setValue($value);

                    $manager->persist($param);
                }
            }
        }

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
        return ['main', 'fill'];
    }
}