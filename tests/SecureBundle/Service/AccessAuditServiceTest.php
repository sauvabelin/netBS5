<?php

declare(strict_types=1);

namespace App\Tests\SecureBundle\Service;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\Membre;
use NetBS\SecureBundle\Audit\Provenance;
use NetBS\SecureBundle\Entity\Autorisation;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Service\AccessAuditService;
use PHPUnit\Framework\TestCase;

class AccessAuditServiceTest extends TestCase
{
    public function test_audits_user_combining_all_three_sources(): void
    {
        // Roles
        $roleAdmin    = (new Role())->setRole('ROLE_ADMIN');
        $roleTresorier = (new Role())->setRole('ROLE_TRESORIER');
        $roleApmbs    = (new Role())->setRole('ROLE_APMBS');

        // Groupes
        $groupeA = new BSGroupe();
        $groupeA->setNom('A');
        $groupeB = new BSGroupe();
        $groupeB->setNom('B');

        // Fonction with TRESORIER role
        $fonction = new Fonction();
        $fonction->setNom('Trésorier');
        $fonction->setAbbreviation('Tr');
        $fonction->addRole($roleTresorier);

        // Membre
        $membre = new Membre();

        // Attribution: active (1 year ago → 1 year in future)
        $attribution = new Attribution();
        $attribution->setFonction($fonction);
        $attribution->setGroupe($groupeA);
        $attribution->setDateDebut((new \DateTime())->modify('-1 year'));
        $attribution->setDateFin((new \DateTime())->modify('+1 year'));
        $membre->addAttribution($attribution);

        // BSUser with direct role
        $user = new BSUser();
        $user->setUsername('testuser');
        $user->setMembre($membre);
        $user->addRole($roleAdmin);

        // Autorisation on groupeB with APMBS role
        $autorisation = new Autorisation();
        $autorisation->setUser($user);
        $autorisation->setGroupe($groupeB);
        $autorisation->getRoles()->add($roleApmbs);
        $user->addAutorisation($autorisation);

        // Act
        $report = $this->makeService()->auditUser($user);

        // Assert: 3 grants total
        $this->assertCount(3, $report->grants);

        // Index by provenance value
        $byProvenance = [];
        foreach ($report->grants as $grant) {
            $byProvenance[$grant->provenance->value] = $grant;
        }

        $this->assertArrayHasKey(Provenance::DIRECT_ROLE->value, $byProvenance);
        $this->assertArrayHasKey(Provenance::FONCTION_ROLE->value, $byProvenance);
        $this->assertArrayHasKey(Provenance::AUTORISATION->value, $byProvenance);

        // DIRECT_ROLE has ROLE_ADMIN
        $directGrant = $byProvenance[Provenance::DIRECT_ROLE->value];
        $directRoleNames = array_map(fn($r) => $r->getRole(), $directGrant->roles);
        $this->assertContains('ROLE_ADMIN', $directRoleNames);

        // FONCTION_ROLE scope is groupeA
        $fonctionGrant = $byProvenance[Provenance::FONCTION_ROLE->value];
        $this->assertSame($groupeA, $fonctionGrant->scope);

        // AUTORISATION scope is groupeB
        $autorisationGrant = $byProvenance[Provenance::AUTORISATION->value];
        $this->assertSame($groupeB, $autorisationGrant->scope);
    }

    public function test_audits_user_with_fonction_role_labels_source_fonction_and_scope(): void
    {
        $roleX = (new Role())->setRole('ROLE_X');

        $groupe = new BSGroupe();
        $groupe->setNom('TestGroupe');

        $fonction = new Fonction();
        $fonction->setNom('Trésorier');
        $fonction->setAbbreviation('Tr');
        $fonction->addRole($roleX);

        $membre = new Membre();

        $attribution = new Attribution();
        $attribution->setFonction($fonction);
        $attribution->setGroupe($groupe);
        $attribution->setDateDebut((new \DateTime())->modify('-1 year'));
        $attribution->setDateFin((new \DateTime())->modify('+1 year'));
        $membre->addAttribution($attribution);

        $user = new BSUser();
        $user->setUsername('fonctionuser');
        $user->setMembre($membre);
        // No direct roles, no autorisations

        $report = $this->makeService()->auditUser($user);

        $this->assertCount(1, $report->grants);

        $grant = $report->grants[0];
        $this->assertSame(Provenance::FONCTION_ROLE, $grant->provenance);
        $this->assertSame($fonction, $grant->sourceFonction);
        $this->assertSame($groupe, $grant->scope);
    }

    public function test_expands_role_tree_recursively_within_a_grant(): void
    {
        $parent = (new Role())->setRole('ROLE_PARENT');
        $child  = (new Role())->setRole('ROLE_CHILD');

        // addChild is the confirmed method in BaseRole::addChild
        $parent->addChild($child);

        $user = new BSUser();
        $user->setUsername('recursiveuser');
        $user->addRole($parent);

        $report = $this->makeService()->auditUser($user);

        $this->assertCount(1, $report->grants);
        $grant = $report->grants[0];

        $roleNames = array_map(fn($r) => $r->getRole(), $grant->roles);
        $this->assertContains('ROLE_PARENT', $roleNames);
        $this->assertContains('ROLE_CHILD', $roleNames);
    }

    public function test_audits_scope_groupe_walks_parent_chain(): void
    {
        $root = $this->makeGroupe('root');
        $mid  = $this->makeGroupe('mid');
        $leaf = $this->makeGroupe('leaf');
        $mid->setParent($root);
        $leaf->setParent($mid);

        $userOnRoot = new BSUser();
        $userOnRoot->setUsername('on_root');
        $autoRoot = new Autorisation();
        $autoRoot->setUser($userOnRoot);
        $autoRoot->setGroupe($root);
        $autoRoot->getRoles()->add($this->makeRole('ROLE_X'));
        $userOnRoot->addAutorisation($autoRoot);

        $userOnLeaf = new BSUser();
        $userOnLeaf->setUsername('on_leaf');
        $autoLeaf = new Autorisation();
        $autoLeaf->setUser($userOnLeaf);
        $autoLeaf->setGroupe($leaf);
        $autoLeaf->getRoles()->add($this->makeRole('ROLE_Y'));
        $userOnLeaf->addAutorisation($autoLeaf);

        $service = $this->makeServiceWithFinders(
            autorisations: [
                $root->getNom() => [$autoRoot],
                $mid->getNom()  => [],
                $leaf->getNom() => [$autoLeaf],
            ],
            attributions: [],
        );

        $report = $service->auditScope($leaf);

        $usernames = array_map(fn($entry) => $entry->user->getUsername(), $report->entries);
        sort($usernames);
        $this->assertSame(['on_leaf', 'on_root'], $usernames);
    }

    private function makeService(): AccessAuditService
    {
        return new AccessAuditService(
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\NetBS\SecureBundle\Service\SecureConfig::class),
            $this->createMock(\NetBS\FichierBundle\Service\FichierConfig::class),
        );
    }

    private function makeServiceWithFinders(array $autorisations, array $attributions): AccessAuditService
    {
        $service = $this->makeService();

        $flatten = static function (array $groupes, array $byName): array {
            $out = [];
            foreach ($groupes as $g) {
                foreach ($byName[$g->getNom()] ?? [] as $item) {
                    $out[] = $item;
                }
            }
            return $out;
        };

        $autoRef = new \ReflectionProperty(AccessAuditService::class, 'autorisationFinder');
        $autoRef->setAccessible(true);
        $autoRef->setValue($service, fn(array $groupes) => $flatten($groupes, $autorisations));

        $attrRef = new \ReflectionProperty(AccessAuditService::class, 'attributionFinder');
        $attrRef->setAccessible(true);
        $attrRef->setValue($service, fn(array $groupes) => $flatten($groupes, $attributions));

        return $service;
    }

    private function makeGroupe(string $nom): BSGroupe
    {
        $g = new BSGroupe();
        $g->setNom($nom);
        return $g;
    }

    private function makeRole(string $role): Role
    {
        return (new Role())->setRole($role);
    }
}
