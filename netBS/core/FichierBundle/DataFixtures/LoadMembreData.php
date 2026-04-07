<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use App\Entity\BSGroupe;
use NetBS\FichierBundle\Entity\Distinction;
use NetBS\FichierBundle\Entity\Famille;
use NetBS\FichierBundle\Entity\Fonction;
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

    public function load(ObjectManager $manager): void
    {
        $c = $this->fichierConfig;

        // [prenom, sexe, groupe_ref, fonction_ref, famille_idx, distinction_ref (optional)]
        $membres = [
            // --- EDC ---
            ['Alexandre',  Personne::HOMME, 'gr_edc',       'fn_Cdt',    1],
            ['Camille',    Personne::FEMME, 'gr_edc',       'fn_QM',     2],
            ['Nicolas',    Personne::HOMME, 'gr_edc',       'fn_SG',     3],

            // --- Branche éclaireurs - zanfleuron ---
            ['Maxime',     Personne::HOMME, 'gr_zanfleuron','fn_CT',     1, 'd8'],
            ['Julien',     Personne::HOMME, 'gr_zanfleuron','fn_CTA',    4],
            ['Lucas',      Personne::HOMME, 'pat_bouquetins','fn_CP',    2, 'd0'],
            ['Samuel',     Personne::HOMME, 'pat_bouquetins','fn_Gars',  5],
            ['David',      Personne::HOMME, 'pat_bouquetins','fn_Gars',  3],
            ['Etienne',    Personne::HOMME, 'pat_castors',  'fn_CP',     6, 'd1'],
            ['Raphaël',    Personne::HOMME, 'pat_castors',  'fn_Gars',   1],
            ['Olivier',    Personne::HOMME, 'pat_lynx',     'fn_CP',     4],
            ['Simon',      Personne::HOMME, 'pat_lynx',     'fn_Gars',   2],

            // --- Branche éclaireuses - solalex ---
            ['Léonie',     Personne::FEMME, 'gr_solalex',   'fn_CT',     3, 'd9'],
            ['Clara',      Personne::FEMME, 'pat_hirondelles','fn_CP',   5, 'd0'],
            ['Anaïs',      Personne::FEMME, 'pat_hirondelles','fn_Gars', 6],
            ['Margaux',    Personne::FEMME, 'pat_ratons',   'fn_CP',     1],
            ['Elise',      Personne::FEMME, 'pat_ratons',   'fn_Gars',   4],
            ['Zoé',        Personne::FEMME, 'pat_goelands', 'fn_CP',     2, 'd1'],

            // --- Branche louveteaux - Mont-d'Or ---
            ['Théo',       Personne::HOMME, 'gr_montdor',   'fn_CM',     3],
            ['Nathan',     Personne::HOMME, 'gr_montdor',   'fn_CL',     5],
            ['Arthur',     Personne::HOMME, 'gr_montdor',   'fn_lvtx',   6],
            ['Hugo',       Personne::HOMME, 'gr_montdor',   'fn_lvtx',   1],

            // --- Branche louvettes - Chenaulaz ---
            ['Emma',       Personne::FEMME, 'gr_chenaulaz', 'fn_CM',     2],
            ['Léa',        Personne::FEMME, 'gr_chenaulaz', 'fn_CL',     4],
            ['Manon',      Personne::FEMME, 'gr_chenaulaz', 'fn_lvtx',   6],

            // --- 3ème branche ---
            ['Gabriel',    Personne::HOMME, 'gr_rovereaz',  'fn_rouge',  1, 'd4'],
            ['Lara',       Personne::FEMME, 'gr_rovereaz',  'fn_rouge',  3],

            // --- Clan ---
            ['Vincent',    Personne::HOMME, 'gr_le_clan',   'fn_CCl',    5],
        ];

        $familyCount = 6;

        foreach ($membres as $md) {
            $prenom       = $md[0];
            $sexe         = $md[1];
            $groupeRef    = $md[2];
            $fonctionRef  = $md[3];
            $familleIdx   = $md[4];
            $distinctRef  = $md[5] ?? null;

            $naissance = \DateTime::createFromFormat('d.m.Y',
                mt_rand(1, 25) . '.' . mt_rand(1, 12) . '.' . mt_rand(1995, 2010));
            $debut = new \DateTime();
            $debut->sub(\DateInterval::createFromDateString(mt_rand(30, 365) . ' days'));

            $attr = $c->createAttribution();
            $attr->setFonction($this->getReference($fonctionRef, Fonction::class))
                 ->setGroupe($this->getReference($groupeRef, BSGroupe::class))
                 ->setDateDebut($debut);

            $membre = $c->createMembre();
            $membre->setStatut(BaseMembre::INSCRIT)
                   ->addAttribution($attr)
                   ->setNaissance($naissance)
                   ->setPrenom($prenom)
                   ->setSexe($sexe)
                   ->setFamille($this->getReference('famille' . $familleIdx, Famille::class));

            if ($distinctRef) {
                $od = $c->createObtentionDistinction();
                $od->setDistinction($this->getReference($distinctRef, Distinction::class))
                   ->setDate(new \DateTime());
                $membre->addObtentionDistinction($od);
            }

            $manager->persist($membre);
        }

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fill'];
    }

    public function getOrder(): int
    {
        return 1500;
    }
}
