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

    public function load(ObjectManager $manager): void
    {
        $config = $this->fichierConfig;

        // Real distinctions from Brigade de Sauvabelin
        $distinctions = [
            '1ère classe', '2ème classe', '2ème classe (expert)',
            'Aspirant / 3ème classe', 'Formation corde',
            'Spécialité clairon', 'Cravate Bleue (EMBS)', 'Cordon rouge',
            'Cours B', 'Cours A', 'Sardine 1', 'Sardine 2',
            'Cours Panorama', 'Cours Top + Coach', 'Cours Expert', 'Sardine 3',
            'SCP', 'Sous-Sizenier/ère', 'Sizenier/Sizenière',
            'Spécialité Cuistot', 'Spécialité Athlète', 'Perfectionnement',
            'Spécialité Acteur', 'Spécialité Recycleur', 'Spécialité bout-en-train',
            'Specialite Chroniqueur', 'Specialite Montrier', 'Specialite Photographe',
            'Specialite Infirmier', 'Spécialité Reporter', 'Spécialité Galant',
            'Spécialité Astronaute', 'Spécialité Secouriste', 'Spécialité Topographe',
            'Spécialité Feu', 'Patte Tendre', 'Spécialité Chanteur',
            'Spécialité Robinson Crusoé', 'Spécialité recruteur', 'Crèpe',
        ];

        foreach ($distinctions as $k => $distinction) {
            $dist = $config->createDistinction($distinction);
            $manager->persist($dist);
            $this->addReference('d' . $k, $dist);
        }

        // Real fonctions from Brigade de Sauvabelin
        $fonctions = [
            'commandant·ex'                     => [10000, 'Cdt'],
            'commandant·ex désigné·ex'          => [9999, 'CdtDes'],
            'quartier-maitre'                   => [8000, 'QM'],
            'quartier-maitre adjoint'           => [7500, 'QMA'],
            'secrétaire général'                => [6000, 'SG'],
            'trésorier'                         => [6000, 'Trés.'],
            'responsable communication'         => [6000, 'R..com'],
            'responsable informatique'          => [1337, 'Resp. IT'],
            'chef de branche'                   => [5000, 'CB'],
            'chef de branche adjoint'           => [1000, 'CBA'],
            'chef de branche louveteaux'        => [5000, 'CBL'],
            'chef de branche louvettes'         => [5000, 'CBLe'],
            'chef de branche éclaireurs'        => [5000, 'CBE'],
            'chef de branche éclaireuses'       => [5000, 'CBEe'],
            'chef de branche rouges'            => [5000, 'CBP'],
            'membre edc'                        => [3000, 'MEqCdt'],
            'chef de clan'                      => [1050, 'CCl'],
            'chef de meute'                     => [1000, 'CM'],
            'chef de meute adjoint'             => [700, 'CMA'],
            'chef de troupe'                    => [1000, 'CT'],
            'chef de troupe adjoint'            => [700, 'CTA'],
            'chef de patrouille'                => [100, 'CP'],
            'chef louveteaux/louvettes'         => [100, 'CL'],
            'éclaireur ou éclaireuse'           => [10, 'Gars'],
            'louveteau ou louvette'             => [10, 'lvtx'],
            'routier'                           => [100, 'rouge'],
            'adjoint'                           => [250, 'Adj'],
            'chef d\'équipe'                    => [300, 'CE'],
            'adjoint chef d\'équipe'            => [250, 'chef d\'équipe adj'],
            'Chef de camp d\'été'               => [300, 'CCEté'],
            'Chef de la Saint-Georges'          => [250, 'CS-G'],
            'chef du matériel'                  => [1000, 'CMat'],
            'responsable économat'              => [1000, 'Eco'],
            'Chef tentes'                       => [1000, 'chef tentes'],
            'admin netBS'                       => [1337, 'ADMIN'],
        ];

        foreach ($fonctions as $nom => $d) {
            $fonction = $config->createFonction();
            $fonction->setNom($nom)->setPoids($d[0])->setAbbreviation($d[1]);
            $manager->persist($fonction);
            $this->addReference('fn_' . $d[1], $fonction);
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
