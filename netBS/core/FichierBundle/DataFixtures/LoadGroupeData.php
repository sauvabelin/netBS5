<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\FichierBundle\Service\FichierConfig;

class LoadGroupeData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private $fichierConfig;

    public function __construct(FichierConfig $config)
    {
        $this->fichierConfig = $config;
    }

    public function load(ObjectManager $manager): void
    {
        $c = $this->fichierConfig;

        // --- Groupe categories ---
        $categories = [];
        foreach (['Brigade', 'Organe', 'branche', 'unité', 'sous-unité', 'association', 'organisation interne'] as $name) {
            $gc = $c->createGroupeCategorie($name);
            $manager->persist($gc);
            $categories[$name] = $gc;
        }

        // --- Groupe types ---
        $typesDef = [
            'brigade'                 => [false, 'Brigade'],
            'équipe de commandement'  => [true,  'Organe'],
            'état major'              => [true,  'Organe'],
            'équipe interne'          => [true,  'organisation interne'],
            'groupe de travail'       => [true,  'organisation interne'],
            'branche'                 => [false, 'branche'],
            'clan'                    => [true,  'unité'],
            'troupe'                  => [true,  'unité'],
            'équipe'                  => [true,  'unité'],
            'meute'                   => [true,  'unité'],
            'patrouille'              => [true,  'sous-unité'],
            'sizaine'                 => [true,  'sous-unité'],
            'association'             => [true,  'association'],
            'migration'               => [true,  'association'],
            'conseil des anciens'     => [true,  'association'],
        ];

        $types = [];
        foreach ($typesDef as $name => [$affichage, $catName]) {
            $gt = $c->createGroupeType();
            $gt->setNom($name)->setGroupeCategorie($categories[$catName])->setAffichageEffectifs($affichage);
            $manager->persist($gt);
            $types[$name] = $gt;
        }

        $manager->flush();

        // --- Helper to create and persist a group ---
        $makeGroupe = function (string $nom, string $typeName, $parent = null, string $ref = null) use ($c, $manager, $types) {
            $g = $c->createGroupe();
            $g->setNom($nom)->setGroupeType($types[$typeName]);
            if ($parent) {
                $g->setParent($parent);
            }
            $manager->persist($g);
            if ($ref) {
                $this->addReference($ref, $g);
            }
            return $g;
        };

        // --- Top-level groups ---
        $migration = $makeGroupe('migration', 'migration', null, 'gr_migration');
        $apmbs     = $makeGroupe('APMBS', 'association', null, 'gr_apmbs');
        $adabs     = $makeGroupe('ADABS', 'association', null, 'gr_adabs');
        $cda       = $makeGroupe('conseil des anciens', 'conseil des anciens', null, 'gr_cda');

        // --- Brigade de Sauvabelin ---
        $brigade = $makeGroupe('Brigade de Sauvabelin', 'brigade', null, 'gr_brigade');

        // EDC & administration
        $edc     = $makeGroupe('EDC', 'équipe de commandement', $brigade, 'gr_edc');
        $embs    = $makeGroupe('EMBS', 'état major', $brigade, 'gr_embs');
        $admin   = $makeGroupe('Administration', 'association', $brigade, 'gr_admin');
        $makeGroupe('team formation', 'équipe interne', $admin, 'gr_team_formation');
        $makeGroupe('team communication', 'équipe interne', $admin, 'gr_team_comm');
        $makeGroupe('team IT', 'équipe interne', $admin, 'gr_team_it');

        // Logistique
        $logistique = $makeGroupe('Logistique', 'association', $brigade, 'gr_logistique');
        $makeGroupe('Mat', 'équipe interne', $logistique, 'gr_mat');
        $makeGroupe('team cordes', 'équipe interne', $logistique, 'gr_team_cordes');
        $makeGroupe('team durabilité et écologie', 'équipe interne', $logistique, 'gr_team_durable');

        // Cellule de soutien
        $makeGroupe('Cellule de soutien', 'équipe interne', $brigade, 'gr_cellule');

        // Les équipes (branche container)
        $equipBranche = $makeGroupe('Les équipes', 'branche', $brigade, 'gr_equipes_branche');
        $makeGroupe('équipe garçons', 'équipe', $equipBranche, 'gr_equipe_garcons');
        $makeGroupe('équipe filles', 'équipe', $equipBranche, 'gr_equipe_filles');

        // --- Branche louveteaux ---
        $brLvtx = $makeGroupe('branche louveteaux', 'branche', $brigade, 'gr_br_louveteaux');

        $montdor = $makeGroupe('Mont-d\'Or', 'meute', $brLvtx, 'gr_montdor');
        $makeGroupe('panthères', 'sizaine', $montdor);
        $makeGroupe('Koalas', 'sizaine', $montdor);
        $makeGroupe('renards', 'sizaine', $montdor);
        $makeGroupe('kangourous', 'sizaine', $montdor);

        $clairiere = $makeGroupe('clairière', 'meute', $brLvtx, 'gr_clairiere');
        $makeGroupe('ours', 'sizaine', $clairiere);
        $makeGroupe('dauphins', 'sizaine', $clairiere);
        $makeGroupe('chevaux', 'sizaine', $clairiere);

        // --- Branche louvettes ---
        $brLvte = $makeGroupe('branche louvettes', 'branche', $brigade, 'gr_br_louvettes');

        $chenaulaz = $makeGroupe('Chenaulaz', 'meute', $brLvte, 'gr_chenaulaz');
        $makeGroupe('Panthères', 'sizaine', $chenaulaz);
        $makeGroupe('Chouettes', 'sizaine', $chenaulaz);
        $makeGroupe('Ours', 'sizaine', $chenaulaz);
        $makeGroupe('Éléphants', 'sizaine', $chenaulaz);

        $caberu = $makeGroupe('cabéru', 'meute', $brLvte, 'gr_caberu');
        $makeGroupe('opossums', 'sizaine', $caberu);
        $makeGroupe('koalas', 'sizaine', $caberu);
        $makeGroupe('wombats', 'sizaine', $caberu);
        $makeGroupe('quokkas', 'sizaine', $caberu);
        $makeGroupe('Wallabys', 'sizaine', $caberu);

        // --- Branche éclaireurs ---
        $brEcl = $makeGroupe('branche éclaireurs', 'branche', $brigade, 'gr_br_eclaireurs');

        $zanfleuron = $makeGroupe('zanfleuron', 'troupe', $brEcl, 'gr_zanfleuron');
        $makeGroupe('bouquetins', 'patrouille', $zanfleuron, 'pat_bouquetins');
        $makeGroupe('castors', 'patrouille', $zanfleuron, 'pat_castors');
        $makeGroupe('lynx', 'patrouille', $zanfleuron, 'pat_lynx');

        $manloud = $makeGroupe('manloud', 'troupe', $brEcl, 'gr_manloud');
        $makeGroupe('hermines', 'patrouille', $manloud, 'pat_hermines');
        $makeGroupe('taureaux', 'patrouille', $manloud, 'pat_taureaux');

        $laneuvaz = $makeGroupe('la neuvaz', 'troupe', $brEcl, 'gr_laneuvaz');
        $makeGroupe('hérons', 'patrouille', $laneuvaz, 'pat_herons');
        $makeGroupe('loutres', 'patrouille', $laneuvaz, 'pat_loutres');
        $makeGroupe('cigognes', 'patrouille', $laneuvaz, 'pat_cigognes');

        $chandelard = $makeGroupe('chandelard', 'troupe', $brEcl, 'gr_chandelard');
        $makeGroupe('rennes', 'patrouille', $chandelard, 'pat_rennes');
        $makeGroupe('marmottes', 'patrouille', $chandelard, 'pat_marmottes');
        $makeGroupe('poussins-coqs', 'patrouille', $chandelard, 'pat_poussins');
        $makeGroupe('cygnes', 'patrouille', $chandelard, 'pat_cygnes');

        $berisal = $makeGroupe('Berisal', 'troupe', $brEcl, 'gr_berisal');
        $makeGroupe('panthères', 'patrouille', $berisal, 'pat_pantheres_ecl');
        $makeGroupe('faucons', 'patrouille', $berisal, 'pat_faucons');
        $makeGroupe('cerfs', 'patrouille', $berisal, 'pat_cerfs');

        $montfort = $makeGroupe('Montfort', 'troupe', $brEcl, 'gr_montfort');
        $makeGroupe('jean-bart', 'patrouille', $montfort, 'pat_jeanbart');
        $makeGroupe('frégate', 'patrouille', $montfort, 'pat_fregate');
        $makeGroupe('surcouf', 'patrouille', $montfort, 'pat_surcouf');

        $lovegno = $makeGroupe('lovégno', 'troupe', $brEcl, 'gr_lovegno');
        $makeGroupe('phénix', 'patrouille', $lovegno, 'pat_phenix');
        $makeGroupe('cobras', 'patrouille', $lovegno, 'pat_cobras');
        $makeGroupe('tigres', 'patrouille', $lovegno, 'pat_tigres');

        // --- Branche éclaireuses ---
        $brEcle = $makeGroupe('branche éclaireuses', 'branche', $brigade, 'gr_br_eclaireuses');

        $solalex = $makeGroupe('solalex', 'troupe', $brEcle, 'gr_solalex');
        $makeGroupe('hirondelles', 'patrouille', $solalex, 'pat_hirondelles');
        $makeGroupe('ratons-laveurs', 'patrouille', $solalex, 'pat_ratons');
        $makeGroupe('goélands', 'patrouille', $solalex, 'pat_goelands');
        $makeGroupe('gazelles', 'patrouille', $solalex, 'pat_gazelles');

        $grammont = $makeGroupe('grammont', 'troupe', $brEcle, 'gr_grammont');
        $makeGroupe('licornes', 'patrouille', $grammont, 'pat_licornes');
        $makeGroupe('kangourous', 'patrouille', $grammont, 'pat_kangourous');
        $makeGroupe('chevreuils', 'patrouille', $grammont, 'pat_chevreuils');

        $armina = $makeGroupe('armina', 'troupe', $brEcle, 'gr_armina');
        $makeGroupe('impalas', 'patrouille', $armina, 'pat_impalas');
        $makeGroupe('mangoustes', 'patrouille', $armina, 'pat_mangoustes');
        $makeGroupe('coyotes', 'patrouille', $armina, 'pat_coyotes');
        $makeGroupe('caméléons', 'patrouille', $armina, 'pat_cameleons');

        $santis = $makeGroupe('säntis', 'troupe', $brEcle, 'gr_santis');
        $makeGroupe('oryx', 'patrouille', $santis, 'pat_oryx');
        $makeGroupe('condors', 'patrouille', $santis, 'pat_condors');
        $makeGroupe('irbis', 'patrouille', $santis, 'pat_irbis');

        // --- Troisième branche (pionniers/rouges) ---
        $br3 = $makeGroupe('troisième branche', 'branche', $brigade, 'gr_br_3eme');
        $makeGroupe('Rovéréaz', 'troupe', $br3, 'gr_rovereaz');
        $makeGroupe('orzival', 'troupe', $br3, 'gr_orzival');
        $makeGroupe('tsalion', 'troupe', $br3, 'gr_tsalion');
        $makeGroupe('Sésal', 'troupe', $br3, 'gr_sesal');
        $makeGroupe('Tamaro', 'troupe', $br3, 'gr_tamaro');

        // --- Quatrième branche (clans) ---
        $br4 = $makeGroupe('quatrième branche', 'branche', $brigade, 'gr_br_4eme');
        $makeGroupe('le clan', 'clan', $br4, 'gr_le_clan');
        $makeGroupe('gamaïun', 'clan', $br4, 'gr_gamaiun');

        // --- Branche Someo ---
        $brSomeo = $makeGroupe('branche Someo', 'branche', $brigade, 'gr_br_someo');
        $makeGroupe('Someo', 'troupe', $brSomeo, 'gr_someo');

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['fill'];
    }

    public function getOrder(): int
    {
        return 990;
    }
}
