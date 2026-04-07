<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Service\FichierConfig;

class LoadFamilleData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $fichierConfig;

    public function __construct(FichierConfig $config)
    {
        $this->fichierConfig = $config;
    }

    public function load(ObjectManager $manager): void
    {
        $c = $this->fichierConfig;

        $families = [
            [
                'nom' => 'Rochat',
                'rue' => 'Avenue de Sauvabelin 12',
                'npa' => '1005',
                'localite' => 'Lausanne',
                'mere' => ['Isabelle', Personne::FEMME, BaseGeniteur::MERE, 'Enseignante'],
                'pere' => ['Philippe', Personne::HOMME, BaseGeniteur::PERE, 'Ingénieur'],
                'tel' => '0213124455',
                'email' => 'rochat.famille@bluewin.ch',
            ],
            [
                'nom' => 'Favre',
                'rue' => 'Chemin de Boissonnet 8',
                'npa' => '1010',
                'localite' => 'Lausanne',
                'mere' => ['Catherine', Personne::FEMME, BaseGeniteur::MERE, 'Médecin'],
                'tel' => '0216478899',
            ],
            [
                'nom' => 'Muller',
                'rue' => 'Route de Berne 45',
                'npa' => '1010',
                'localite' => 'Lausanne',
                'pere' => ['Thomas', Personne::HOMME, BaseGeniteur::PERE, 'Architecte'],
                'mere' => ['Nathalie', Personne::FEMME, BaseGeniteur::MERE, 'Avocate'],
                'tel' => '0216331122',
                'email' => 'muller.th@sunrise.ch',
            ],
            [
                'nom' => 'Bonvin',
                'rue' => 'Rue du Valentin 3',
                'npa' => '1004',
                'localite' => 'Lausanne',
                'mere' => ['Marie', Personne::FEMME, BaseGeniteur::MERE, 'Infirmière'],
                'tel' => '0213207788',
            ],
            [
                'nom' => 'Thévenaz',
                'rue' => 'Avenue de la Gare 21',
                'npa' => '1003',
                'localite' => 'Lausanne',
                'pere' => ['Jean-Marc', Personne::HOMME, BaseGeniteur::PERE, 'Professeur'],
                'mere' => ['Sylvie', Personne::FEMME, BaseGeniteur::MERE, 'Pharmacienne'],
                'email' => 'thevenaz@gmx.ch',
            ],
            [
                'nom' => 'Despont',
                'rue' => 'Chemin des Fleurettes 7',
                'npa' => '1007',
                'localite' => 'Lausanne',
                'mere' => ['Valérie', Personne::FEMME, BaseGeniteur::MERE, 'Graphiste'],
                'tel' => '0216559933',
            ],
        ];

        foreach ($families as $i => $data) {
            $adresse = $c->createAdresse();
            $adresse->setRue($data['rue'])->setNpa($data['npa'])->setLocalite($data['localite'])->setExpediable(true);

            $famille = $c->createFamille();
            $famille->setNom($data['nom'])->addAdresse($adresse);

            if (isset($data['mere'])) {
                $g = $c->createGeniteur();
                $g->setStatut($data['mere'][2])->setPrenom($data['mere'][0])
                  ->setSexe($data['mere'][1])->setProfession($data['mere'][3]);
                $famille->addGeniteur($g);
            }
            if (isset($data['pere'])) {
                $g = $c->createGeniteur();
                $g->setStatut($data['pere'][2])->setPrenom($data['pere'][0])
                  ->setSexe($data['pere'][1])->setProfession($data['pere'][3]);
                $famille->addGeniteur($g);
            }
            if (isset($data['tel'])) {
                $famille->addTelephone($c->createTelephone($data['tel']));
            }
            if (isset($data['email'])) {
                // Add email to first geniteur
                $geniteurs = $famille->getGeniteurs();
                if (count($geniteurs) > 0) {
                    $geniteurs[0]->addEmail($c->createEmail($data['email']));
                }
            }

            $manager->persist($famille);
            $this->addReference('famille' . ($i + 1), $famille);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fill'];
    }

    public function getOrder(): int
    {
        return 1000;
    }
}
