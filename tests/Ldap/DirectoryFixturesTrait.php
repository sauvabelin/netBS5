<?php

declare(strict_types=1);

namespace App\Tests\Ldap;

use Doctrine\DBAL\Connection;

/**
 * Directory fixtures inserted with plain SQL (so the attribution subscriber
 * doesn't create accounts or send mail). Callers wrap them in a transaction
 * and roll it back.
 */
trait DirectoryFixturesTrait
{
    private Connection $db;

    /**
     * Type A (creates a group, "tous") with groupe GA <- child GB of type B
     * (doesn't), plus GC of type B. One active nextcloud user is attributed
     * in GB.
     *
     * @return array{ga: int, gb: int, gc: int}
     */
    private function seedGroupeTree(): array
    {
        $categorie = $this->insert('netbs_fichier_groupe_categories', ['nom' => 'dirproj-categorie']);
        $typeA = $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-a', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie, 'cree_groupe' => 1, 'sous_groupes' => 'tous']);
        $typeB = $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-b', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie, 'cree_groupe' => 0, 'sous_groupes' => 'tous']);
        $ga = $this->groupe('dirproj-GA', $typeA, null);
        $gb = $this->groupe('dirproj-GB', $typeB, $ga);
        $gc = $this->groupe('dirproj-GC', $typeB, null);

        $fonction = $this->insert('netbs_fichier_fonctions', ['nom' => 'dirproj-fonction', 'abbreviation' => 'dp', 'compte_dans_parent' => 0]);
        $membre = $this->insert('sauvabelin_netbs_membres', [
            'naissance' => '2010-01-01', 'inscription' => '2020-01-01', 'statut' => 'inscrit',
            'nom' => 'Projection', 'prenom' => 'Dir', 'sexe' => 'm',
            'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);
        $this->insert('sauvabelin_netbs_users', [
            'membre_id' => $membre, 'nextcloud_account' => 1, 'nextcloud_admin' => 0, 'nextcloud_quota' => 0,
            'wiki_account' => 0, 'new_password_required' => 0, 'wiki_admin' => 0,
            'login_username' => 'dirproj.user', 'username' => 'dirproj.user', 'password' => 'x',
            'isActive' => 1, 'dateAdded' => $this->now(),
        ]);
        $this->insert('netbs_fichier_attributions', [
            'membre_id' => $membre, 'groupe_id' => $gb, 'fonction_id' => $fonction,
            'dateDebut' => '2020-01-01', 'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);

        return ['ga' => $ga, 'gb' => $gb, 'gc' => $gc];
    }

    private function groupe(string $nom, int $type, ?int $parent): int
    {
        return $this->insert('sauvabelin_netbs_groupes', ['nom' => $nom, 'validity' => 'ouvert', 'nc_mapped' => 0, 'groupeType_id' => $type, 'parent_id' => $parent]);
    }

    /** @param array<string, mixed> $row */
    private function insert(string $table, array $row): int
    {
        $this->db->insert($table, $row);
        return (int) $this->db->lastInsertId();
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
