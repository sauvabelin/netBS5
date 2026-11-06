<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Service\FichierConfig;

/**
 * Points the legacy `bs.*_id` keys (read by netBS through BsReferences and by the Nextcloud SQL
 * views) at the dummy structure, and sets the galerie password, so a `netbs:install --dummy=true`
 * ends with nothing to bind by hand. Fails loudly if a row it expects is missing.
 */
class LoadBsSettingsData extends Fixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    private const GROUPES = [
        'groupe.adabs_id'               => 'ADABS',
        'groupe.apmbs_id'               => 'APMBS',
        'groupe.branche_eclaireurs_id'  => 'branche éclaireurs',
        'groupe.branche_eclaireuses_id' => 'branche éclaireuses',
        'groupe.branche_louveteaux_id'  => 'branche louveteaux',
        'groupe.branche_louvettes_id'   => 'branche louvettes',
        'groupe.branche_smt_id'         => 'branche Someo',
    ];

    private const FONCTIONS = [
        'fonction.cp_id'        => 'chef de patrouille',
        'fonction.cl_id'        => 'chef louveteaux/louvettes',
        'fonction.rouge_id'     => 'routier',
        'fonction.louveteau_id' => 'louveteau ou louvette',
        'fonction.eclaireur_id' => 'éclaireur ou éclaireuse',
        'fonction.equipier_id'  => 'équipier ou équipière',
    ];

    private const GROUPE_TYPES = [
        'groupe_type.branche_id'        => 'branche',
        'groupe_type.troupe_id'         => 'troupe',
        'groupe_type.meute_id'          => 'meute',
        'groupe_type.clan_id'           => 'clan',
        'groupe_type.association_id'    => 'association',
        'groupe_type.edc_id'            => 'équipe de commandement',
        'groupe_type.equipe_interne_id' => 'équipe interne',
        'groupe_type.equipe_id'         => 'équipe',
        'groupe_type.brigade_id'        => 'brigade',
        'groupe_type.cda_id'            => 'conseil des anciens',
    ];

    public function __construct(
        private FichierConfig $config,
        private ParameterManager $params,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // The dummy structure has no équipier fonction; the Nextcloud views need one.
        if (!$manager->getRepository($this->config->getFonctionClass())->findOneBy(['nom' => 'équipier ou équipière'])) {
            $equipier = $this->config->createFonction();
            $equipier->setNom('équipier ou équipière')->setPoids(10)->setAbbreviation('Eq');
            $manager->persist($equipier);
            $manager->flush();
        }

        $this->setReferences($manager, $this->config->getGroupeClass(), self::GROUPES);
        $this->setReferences($manager, $this->config->getFonctionClass(), self::FONCTIONS);
        $this->setReferences($manager, $this->config->getGroupeTypeClass(), self::GROUPE_TYPES);
        $this->setReferences($manager, $this->config->getGroupeCategorieClass(), ['groupe_categorie.unite_id' => 'unité']);

        $this->params->setValue('bs', 'fonction.weight.user_account', '100');
        $this->params->setValue('galerie', 'parent_token', 'parents-dev');
    }

    /** @param array<string,string> $keys setting key => row name */
    private function setReferences(ObjectManager $manager, string $class, array $keys): void
    {
        foreach ($keys as $key => $nom) {
            $row = $manager->getRepository($class)->findOneBy(['nom' => $nom]);

            if (!$row) {
                throw new \RuntimeException(sprintf('LoadBsSettingsData: no %s named "%s" for bs.%s.', $class, $nom, $key));
            }

            $this->params->setValue('bs', $key, (string) $row->getId());
        }
    }

    public static function getGroups(): array
    {
        return ['fill'];
    }

    public function getOrder(): int
    {
        return 1100;
    }
}
