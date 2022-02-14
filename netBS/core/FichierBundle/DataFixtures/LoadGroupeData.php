<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadGroupeData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
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
        $config     = $this->fichierConfig;

        $gc         = $config->createGroupeCategorie('unité');
        $gt         = $config->createGroupeType();

        $gt->setNom('troupe')->setGroupeCategorie($gc)->setAffichageEffectifs(false);
        $manager->persist($gc);
        $manager->persist($gt);

        $troupe = $config->createGroupe();
        $troupe->setNom('Les cultivables')->setGroupeType($gt);
        $manager->persist($troupe);

        $this->addReference('troupe', $troupe);

        $gc     = $config->createGroupeCategorie('sous-unité');
        $gt     = $config->createGroupeType();
        $gt->setNom('patrouille')->setGroupeCategorie($gc)->setAffichageEffectifs(true);

        $manager->persist($gc);
        $manager->persist($gt);


        $groupe = $config->createGroupe();
        $groupe->setNom('Les betteraves')->setParent($troupe)->setGroupeType($gt);
        $manager->persist($groupe);

        $this->addReference('p1', $groupe);

        $groupe = $config->createGroupe();
        $groupe->setNom('Les cannes à sucre')->setParent($troupe)->setGroupeType($gt);
        $manager->persist($groupe);

        $this->addReference('p2', $groupe);
        $manager->flush();
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
        return 990;
    }
}