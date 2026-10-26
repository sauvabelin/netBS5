<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

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
     * @return array{ga: int, gb: int, gc: int, fonction: int, membre: int, user: int, attribution: int}
     */
    private function seedGroupeTree(): array
    {
        $categorie = $this->insert('netbs_fichier_groupe_categories', ['nom' => 'dirproj-categorie']);
        $typeA = $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-a', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie, 'cree_groupe' => 1, 'sous_groupes' => 'tous']);
        $typeB = $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-b', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie, 'cree_groupe' => 0, 'sous_groupes' => 'tous']);
        $ga = $this->groupe('dirproj-GA', $typeA, null);
        $gb = $this->groupe('dirproj-GB', $typeB, $ga);
        $gc = $this->groupe('dirproj-GC', $typeB, null);

        $fonction = $this->insert('netbs_fichier_fonctions', ['nom' => 'dirproj-fonction', 'abbreviation' => 'dp']);
        $membre = $this->insert('sauvabelin_netbs_membres', [
            'naissance' => '2010-01-01', 'inscription' => '2020-01-01', 'statut' => 'inscrit',
            'nom' => 'Projection', 'prenom' => 'Dir', 'sexe' => 'm',
            'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);
        $user = $this->seedUser('dirproj.user', ['membre_id' => $membre]);
        $attribution = $this->insert('netbs_fichier_attributions', [
            'membre_id' => $membre, 'groupe_id' => $gb, 'fonction_id' => $fonction,
            'dateDebut' => '2020-01-01', 'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);

        return ['ga' => $ga, 'gb' => $gb, 'gc' => $gc, 'fonction' => $fonction, 'membre' => $membre, 'user' => $user, 'attribution' => $attribution];
    }

    /** Active account with nextcloud_account = 1, unless $overrides says otherwise. */
    private function seedUser(string $username, array $overrides = []): int
    {
        return $this->insert('sauvabelin_netbs_users', $overrides + [
            'membre_id' => null, 'nextcloud_account' => 1, 'nextcloud_admin' => 0, 'nextcloud_quota' => 0,
            'wiki_account' => 0, 'new_password_required' => 0, 'wiki_admin' => 0,
            'login_username' => $username, 'username' => $username, 'password' => 'x',
            'isActive' => 1, 'dateAdded' => $this->now(),
        ]);
    }

    private function grantUserRole(int $userId, string $role): void
    {
        $this->db->insert('bsuser_role', ['bsuser_id' => $userId, 'role_id' => $this->role($role)]);
    }

    private function grantFonctionRole(int $fonctionId, string $role): void
    {
        $this->db->insert('fonction_role', ['fonction_id' => $fonctionId, 'role_id' => $this->role($role)]);
    }

    /** Id of the role, created if the test DB doesn't have it yet. */
    private function role(string $name): int
    {
        $id = $this->db->fetchOne('SELECT id FROM netbs_secure_roles WHERE role = ?', [$name]);
        return $id !== false ? (int) $id : $this->insert('netbs_secure_roles', ['role' => $name, 'poids' => 0, 'description' => '']);
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
