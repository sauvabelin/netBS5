<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Service\FichierConfig;

class LoadMembreData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
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
        $config             = $this->fichierConfig;

        $data   = [
            ['Alphonse', Personne::HOMME, $this->getReference('p1'), $this->getReference('CP'), $this->getReference('d1')],
            ['Marc', Personne::HOMME, $this->getReference('p1'), $this->getReference('gars')],
            ['André', Personne::HOMME, $this->getReference('p1'), $this->getReference('gars'), $this->getReference('d2')],
            ['Jean', Personne::HOMME, $this->getReference('troupe'), $this->getReference('CT')],
            ['Romain', Personne::HOMME, $this->getReference('p2'), $this->getReference('CP')],
            ['Charles', Personne::HOMME, $this->getReference('p2'), $this->getReference('gars')],
            ['Anthony', Personne::HOMME, $this->getReference('p2'), $this->getReference('gars')],
            ['Joe', Personne::HOMME, $this->getReference('p2'), $this->getReference('gars')],
        ];

        foreach($data as $md) {

            $naissance  = \DateTime::createFromFormat('d.m.Y', mt_rand(1,25) . "." . mt_rand(1,12) . '.' . mt_rand(1995, 2000));
            $debut  = new \DateTime();
            $debut->sub(\DateInterval::createFromDateString(mt_rand(2,10) . " days"));

            $fin    = null;
            if(mt_rand(0,10) > 5) {
                $fin = new \DateTime();
                $fin->add(\DateInterval::createFromDateString(mt_rand(15, 30) . " days"));
            }

            $attr   = $config->createAttribution();
            $attr->setFonction($md[3])->setGroupe($md[2])->setDateDebut($debut)->setDateFin($fin);

            $membre = $config->createMembre();
            $membre->setStatut(BaseMembre::INSCRIT)->addAttribution($attr)->setNaissance($naissance)->setPrenom($md[0])->setSexe($md[1]);
            $membre->setFamille($this->getReference('famille' . (mt_rand(1,10) > 5 ? 1 : 2) ));

            if(isset($md[4])) {

                $od = $config->createObtentionDistinction();
                $od->setDistinction($md[4])->setDate(new \DateTime());
                $membre->addObtentionDistinction($od);
            }

            $manager->persist($membre);
            $manager->flush();
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
        return 1500;
    }
}