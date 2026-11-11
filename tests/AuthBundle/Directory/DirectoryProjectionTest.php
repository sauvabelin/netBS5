<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\GroupOutline;
use NetBS\CoreBundle\Rule\Rule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against netbs5_test, inside a transaction that tearDown rolls back.
 */
final class DirectoryProjectionTest extends KernelTestCase
{
    use DirectoryFixturesTrait;

    private DirectoryProjection $projection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->projection = $container->get(DirectoryProjection::class);
        $this->db->beginTransaction();
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    private function parmi(int ...$groupes): string
    {
        return self::blockRule('groupe.parmi', ['groupes' => $groupes]);
    }

    public function testRefusesWithoutRules(): void
    {
        $this->seedGroupeTree();

        $this->expectException(DirectoryNotConfiguredException::class);
        $this->projection->snapshot();
    }

    public function testRefusesWhenRulesSelectNothing(): void
    {
        $this->seedGroupeTree();
        $categorie = $this->insert('netbs_fichier_groupe_categories', ['nom' => 'dirproj-vide']);
        $empty = $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-vide', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie]);
        $this->publicationRule('dirproj-rien', self::blockRule('groupe.types', ['types' => [$empty]]));

        $this->expectException(DirectoryNotConfiguredException::class);
        $this->projection->snapshot();
    }

    public function testFirstMatchingRuleWins(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-GA seul', $this->parmi($ids['ga']), 'direct');
        $this->publicationRule('dirproj-unités', self::blockRule('groupe.types', ['types' => [$ids['typeA']]]), 'tous');

        $group = $this->projection->snapshot()->groups[$ids['ga']];

        self::assertSame([], $group->members, 'direct mode: GB member not included');
        self::assertStringContainsString('dirproj-GA seul', $group->origin);
    }

    public function testUnmatchedGroupesAreNotPublished(): void
    {
        $ids = $this->seedPublishedTree();

        $groups = $this->projection->snapshot()->groups;

        self::assertSame(['dirproj.user'], $groups[$ids['ga']]->members);
        self::assertArrayNotHasKey($ids['gb'], $groups);
        self::assertArrayNotHasKey($ids['gc'], $groups);
    }

    public function testComputedGroupHoldsMatchingMembresWithUsers(): void
    {
        $ids = $this->seedGroupeTree();
        $orphan = $this->insert('sauvabelin_netbs_membres', [
            'naissance' => '2010-01-01', 'inscription' => '2020-01-01', 'statut' => 'inscrit',
            'nom' => 'Sans compte', 'prenom' => 'Dir', 'sexe' => 'm', 'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);
        $this->insert('netbs_fichier_attributions', [
            'membre_id' => $orphan, 'groupe_id' => $ids['gb'], 'fonction_id' => $ids['fonction'],
            'dateDebut' => '2020-01-01', 'createdAt' => $this->now(), 'updatedAt' => $this->now(),
        ]);
        $id = $this->computedGroup('dirproj-chefs', self::blockRule('membre.attribution_groupes', ['groupes' => [$ids['gb']], 'sous_groupes' => 'aucun']));

        $snapshot = $this->projection->snapshot();
        $group = $snapshot->computedGroups[$id];

        self::assertSame(['dirproj.user'], $group->members);
        self::assertSame($id, $group->computedId);
        self::assertSame("computed:$id", DirectoryGroup::key($group->netbsId, $group->role, $group->computedId));
        self::assertSame([], $snapshot->groups);
    }

    public function testComputedNameReservedBeforeGroupeNames(): void
    {
        $ids = $this->seedPublishedTree();
        $this->computedGroup('dirproj-GA', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));

        $snapshot = $this->projection->snapshot();

        self::assertSame(sprintf('dirproj-GA [%d]', $ids['ga']), $snapshot->groups[$ids['ga']]->name);
        self::assertSame('dirproj-GA', array_values($snapshot->computedGroups)[0]->name);
    }

    public function testDeletedReferenceSuspendsTheSync(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-GC', $this->parmi($ids['gc']));
        $this->publicationRule('dirproj-cassée', $this->parmi(999999999));

        $this->assertSuspendedBy('dirproj-cassée');
    }

    public function testReservedLldapNamesGetASuffix(): void
    {
        $ids = $this->seedPublishedTree();
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'netbs_users'], ['id' => $ids['ga']]);

        self::assertSame(sprintf('netbs_users [%d]', $ids['ga']), $this->projection->snapshot()->groups[$ids['ga']]->name);
    }

    public function testBrokenSousGroupesRuleSuspendsTheSync(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-GA', $this->parmi($ids['ga']), 'regle', '{nope');
        $this->publicationRule('dirproj-GC', $this->parmi($ids['gc']));

        $this->assertSuspendedBy('dirproj-GA');
    }

    public function testSousGroupesRuntimeErrorSuspendsTheSync(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-GA', $this->parmi($ids['ga']), 'regle', Rule::expert('membre.getFamille().getNom() == "x"')->toJson());
        $this->publicationRule('dirproj-GC', $this->parmi($ids['gc']));

        $this->assertSuspendedBy('dirproj-GA');
    }

    public function testExpertRuntimeErrorInComputedGroupSuspendsTheSync(): void
    {
        $this->seedPublishedTree();
        $id = $this->computedGroup('dirproj-cassé', Rule::expert('membre.getFamille().getNom() == "x"')->toJson());

        $this->assertSuspendedBy('dirproj-cassé');
        try {
            $this->projection->group("c$id");
            self::fail('expected DirectoryNotConfiguredException');
        } catch (DirectoryNotConfiguredException $e) {
            self::assertStringContainsString('dirproj-cassé', $e->getMessage());
        }
    }

    public function testProblemsAreReportedPerRule(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-ok', $this->parmi($ids['ga']));
        $this->publicationRule('dirproj-cassée', $this->parmi(999999999));
        $this->computedGroup('dirproj-cassé', '{nope');
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        $problems = [];
        foreach ($em->getRepository(\NetBS\AuthBundle\Entity\DirectoryPublicationRule::class)->findAll() as $rule) {
            $problems[$rule->getLabel()] = $this->projection->ruleProblem($rule);
        }
        foreach ($em->getRepository(\NetBS\AuthBundle\Entity\DirectoryComputedGroup::class)->findAll() as $group) {
            $problems[$group->getName()] = $this->projection->computedProblem($group);
        }

        self::assertNull($problems['dirproj-ok']);
        self::assertNotNull($problems['dirproj-cassée']);
        self::assertNotNull($problems['dirproj-cassé']);
    }

    private function assertSuspendedBy(string ...$needles): void
    {
        try {
            $this->projection->snapshot();
            self::fail('expected DirectoryNotConfiguredException');
        } catch (DirectoryNotConfiguredException $e) {
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $e->getMessage());
            }
        }
    }

    public function testRoleGroupsHoldDirectoryUsersWithTheRole(): void
    {
        $ids = $this->seedPublishedTree();
        $this->grantUserRole($ids['user'], 'ROLE_NEXTCLOUD_ADMIN');
        $this->grantUserRole($this->seedUser('dirproj.inactive', ['isActive' => 0]), 'ROLE_NEXTCLOUD_ADMIN');

        $roleGroups = $this->projection->snapshot()->roleGroups;

        self::assertSame(['ROLE_NEXTCLOUD_ADMIN', 'ROLE_WIKI_ADMIN'], array_keys($roleGroups));
        self::assertSame('nextcloud-admins', $roleGroups['ROLE_NEXTCLOUD_ADMIN']->name);
        self::assertSame('ROLE_NEXTCLOUD_ADMIN', $roleGroups['ROLE_NEXTCLOUD_ADMIN']->role);
        self::assertSame(['dirproj.user'], $roleGroups['ROLE_NEXTCLOUD_ADMIN']->members);
        self::assertSame([], $roleGroups['ROLE_WIKI_ADMIN']->members, 'always present, even empty');
    }

    public function testGroupNamesAreDnSafeUniqueAndAvoidRoleGroupNames(): void
    {
        $ids = $this->seedGroupeTree();
        $this->publicationRule('dirproj-tous', $this->parmi($ids['ga'], $ids['gb'], $ids['gc']));
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'nextcloud-admins'], ['id' => $ids['ga']]);
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'Equipe (x), y'], ['id' => $ids['gb']]);
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'Equipe [x]  y'], ['id' => $ids['gc']]);

        $groups = $this->projection->snapshot()->groups;

        self::assertSame(sprintf('nextcloud-admins [%d]', $ids['ga']), $groups[$ids['ga']]->name);
        self::assertSame('Equipe [x]  y', $groups[$ids['gb']]->name);
        self::assertSame(sprintf('Equipe [x]  y [%d]', $ids['gc']), $groups[$ids['gc']]->name);
    }

    public function testSingleGroupAndOutlinesMatchSnapshot(): void
    {
        $ids = $this->seedPublishedTree();
        $this->grantUserRole($ids['user'], 'ROLE_WIKI_ADMIN');
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'nextcloud-admins'], ['id' => $ids['ga']]);
        $computed = $this->computedGroup('dirproj-chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));

        $snapshot = $this->projection->snapshot();
        static::getContainer()->get('doctrine.orm.entity_manager')->clear();

        self::assertEquals($snapshot->groups[$ids['ga']], $this->projection->group((string) $ids['ga']));
        self::assertEquals($snapshot->roleGroups['ROLE_WIKI_ADMIN'], $this->projection->group('ROLE_WIKI_ADMIN'));
        self::assertEquals($snapshot->computedGroups[$computed], $this->projection->group("c$computed"));
        self::assertNull($this->projection->group((string) $ids['gb']), 'not selected');
        self::assertNull($this->projection->group('ROLE_ADMIN'), 'role not published');
        self::assertNull($this->projection->group('c999999999'));

        $outlines = [];
        foreach ($this->projection->groupOutlines() as $outline) {
            self::assertInstanceOf(GroupOutline::class, $outline);
            $outlines[$outline->key] = $outline;
        }
        self::assertSame(['ROLE_NEXTCLOUD_ADMIN', 'ROLE_WIKI_ADMIN', "c$computed", (string) $ids['ga']], array_map('strval', array_keys($outlines)));
        self::assertSame(sprintf('nextcloud-admins [%d]', $ids['ga']), $outlines[(string) $ids['ga']]->name);
        self::assertSame($ids['ga'], $outlines[(string) $ids['ga']]->groupeId);
        self::assertSame($snapshot->groups[$ids['ga']]->origin, $outlines[(string) $ids['ga']]->origin);
    }

    public function testATemporaryPasswordKeepsTheUserButInNoGroup(): void
    {
        $ids = $this->seedPublishedTree();
        $this->grantUserRole($ids['user'], 'ROLE_NEXTCLOUD_ADMIN');
        $computed = $this->computedGroup('dirproj-chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
        $this->db->update('sauvabelin_netbs_users', ['new_password_required' => 1], ['id' => $ids['user']]);

        $snapshot = $this->projection->snapshot();
        static::getContainer()->get('doctrine.orm.entity_manager')->clear();

        self::assertArrayHasKey('dirproj.user', $snapshot->users);
        self::assertSame([], $snapshot->groups[$ids['ga']]->members);
        self::assertSame([], $snapshot->computedGroups[$computed]->members);
        self::assertSame([], $snapshot->roleGroups['ROLE_NEXTCLOUD_ADMIN']->members);
        self::assertSame([], $this->projection->group((string) $ids['ga'])->members, 'single-group lookup agrees');
        self::assertSame([], $this->projection->group("c$computed")->members);
    }
}
