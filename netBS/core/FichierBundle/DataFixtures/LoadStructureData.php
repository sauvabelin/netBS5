<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\FichierBundle\Service\FichierConfig;

class LoadStructureData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $fichierConfig;

    public function __construct(FichierConfig $config)
    {
        $this->fichierConfig = $config;
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $config         = $this->fichierConfig;

        $distinctions   = ['Badge feu', 'Badge cuistot', 'Badge recycleur', 'Betterave de qualité', 'Culture BIO'];
        foreach($distinctions as $k => $distinction) {

            $dist   = $config->createDistinction($distinction);
            $manager->persist($dist);

            $this->addReference('d' . $k, $dist);
        }

        $fonctions      = [
            'chef de patrouille'    => [10, 'CP'],
            'chef de troupe'        => [100, 'CT'],
            'gars ou fille'         => [1, 'gars'],
            'chef de branche'       => [300, 'CB'],
            'commandant'            => [1000, 'Cdt'],
            'sous-cp'               => [8, 'sCP']
        ];

        foreach($fonctions as $nom => $d) {

            $fonction = $config->createFonction();
            $fonction->setNom($nom)->setPoids($d[0])->setAbbreviation($d[1]);

            $manager->persist($fonction);
            $manager->flush();

            $this->addReference($d[1], $fonction);
        }
    }

    public static function getGroups(): array
    {
        return ['fill'];
    }

    /**
     * Get the order of this fixture
     *
     * @return integer
     */
    public function getOrder()
    {
        return 1000;
    }
}