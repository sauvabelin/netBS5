<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Message\SyncDirectory;
use NetBS\AuthBundle\MessageHandler\SyncDirectoryHandler;
use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The LDAP admin page against netbs5_test (inside a rolled-back transaction)
 * and an in-memory lldap.
 */
final class LdapControllerTest extends WebTestCase
{
    use DirectoryFixturesTrait;
    use UserFactoryTrait;

    private const URL = '/netBS/secure/administration/authentification/ldap';

    private KernelBrowser $client;
    private FakeLldap $lldap;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel for the whole test, so every request sees the open transaction.
        $this->client->disableReboot();
        $this->db = $this->client->getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();

        $this->lldap = new FakeLldap();
        static::getContainer()->set(LldapClientInterface::class, $this->lldap);
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    public function testRequiresAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'ldap-page-user', null, null, ['ROLE_USER']), 'netbs');

        $this->client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListsDirectoryGroupsWithoutTouchingLldap(): void
    {
        $ids = $this->seedPublishedTree();
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        $rowIds = $crawler->filter('#ldap-groups tr[data-row-id]')->each(fn ($tr) => $tr->attr('data-row-id'));
        self::assertSame(['ROLE_NEXTCLOUD_ADMIN', 'ROLE_WIKI_ADMIN'], array_slice($rowIds, 0, 2), 'role groups first');
        self::assertContains((string) $ids['ga'], $rowIds);
        self::assertNotContains((string) $ids['gb'], $rowIds);
        $group = $crawler->filter(sprintf('#ldap-groups tr[data-row-id="%d"]', $ids['ga']));
        self::assertStringContainsString('dirproj-GA', $group->text());
        self::assertStringNotContainsString('Dir Projection', $group->text(), 'members are on the group page');
        self::assertCount(1, $group->filter(sprintf('a[href$="/groups/%d"]', $ids['ga'])));
        self::assertCount(1, $group->filter(sprintf('.ldap-member-count[data-lazy-count-url$="/groups/%d/count"]', $ids['ga'])), 'counted on hover');
        self::assertSame([], $this->lldap->groups, 'the page must not write to lldap');
    }

    public function testMemberCountEndpoint(): void
    {
        $ids = $this->seedPublishedTree();
        $this->loginAdmin();

        $this->client->request('GET', sprintf('%s/groups/%d/count', self::URL, $ids['ga']));
        self::assertResponseIsSuccessful();
        self::assertSame(['count' => 1], json_decode($this->client->getResponse()->getContent(), true));

        $this->client->request('GET', sprintf('%s/groups/%d/count', self::URL, $ids['gb']));
        self::assertResponseStatusCodeSame(404);
    }

    public function testOriginIsEscaped(): void
    {
        $ids = $this->seedPublishedTree();
        $this->db->update('netbs_auth_directory_publication_rules', ['label' => '<b>x</b>'], ['id' => $ids['rule']]);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertCount(0, $crawler->filter(sprintf('#ldap-groups tr[data-row-id="%d"] b', $ids['ga'])));
        self::assertCount(0, $crawler->filter('#ldap-publication-rules td b'));
        self::assertStringContainsString('<b>x</b>', $crawler->filter('#ldap-publication-rules')->text());
    }

    public function testPageShowsRuleSectionsWhenNotConfigured(): void
    {
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#ldap-publication-rules'));
        self::assertCount(1, $crawler->filter('#ldap-computed-groups'));
        self::assertCount(1, $crawler->filter('#ldap-not-configured'));
        self::assertCount(0, $crawler->filter('#ldap-groups'));
    }

    public function testListsRulesAndComputedGroups(): void
    {
        $ids = $this->seedPublishedTree();
        $computed = $this->computedGroup('dirproj-chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertStringContainsString('dirproj-unités', $crawler->filter('#ldap-publication-rules')->text());
        self::assertStringContainsString('dirproj-a', $crawler->filter('#ldap-publication-rules')->text());
        self::assertStringContainsString('dirproj-chefs', $crawler->filter('#ldap-computed-groups')->text());
        self::assertCount(1, $crawler->filter(sprintf('#ldap-groups tr[data-row-id="c%d"]', $computed)));
        self::assertCount(0, $crawler->filter('#ldap-not-configured'));
    }

    public function testBrokenRuleIsFlaggedAndNoGroupIsListed(): void
    {
        $this->seedPublishedTree();
        $this->computedGroup('dirproj-cassé', self::blockRule('membre.fonctions', ['fonctions' => [999999999]]));
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('en erreur', $crawler->filter('#ldap-computed-groups')->text());
        self::assertStringContainsString('suspendue', $crawler->filter('#ldap-not-configured')->text());
        self::assertCount(0, $crawler->filter('#ldap-groups'));
    }

    public function testGroupPageRedirectsWhileARuleIsBroken(): void
    {
        $this->seedPublishedTree();
        $broken = $this->computedGroup('dirproj-cassé', '{nope');
        $this->loginAdmin();

        $this->client->request('GET', self::URL . "/groups/c$broken");

        self::assertResponseRedirects(self::URL);
    }

    public function testComputedGroupPage(): void
    {
        $ids = $this->seedPublishedTree();
        $computed = $this->computedGroup('dirproj-chefs', self::blockRule('membre.fonctions', ['fonctions' => [$ids['fonction']]]));
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL . "/groups/c$computed");

        self::assertResponseIsSuccessful();
        self::assertSame('à ajouter', $this->memberStatus($crawler, 'dirproj.user'));
    }

    public function testCountEndpointReportsBrokenRule(): void
    {
        $this->seedPublishedTree();
        $broken = $this->computedGroup('dirproj-cassé', '{nope');
        $this->loginAdmin();

        $this->client->request('GET', self::URL . "/groups/c$broken/count");

        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('dirproj-cassé', json_decode($this->client->getResponse()->getContent(), true)['error']);
    }

    public function testGroupPageComparesMembersWithLldap(): void
    {
        $ids = $this->seedPublishedTree();
        $this->loginAdmin();
        $url = sprintf('%s/groups/%d', self::URL, $ids['ga']);

        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('pas encore créé', $crawler->filter('#ldap-group')->text());
        self::assertSame('à ajouter', $this->memberStatus($crawler, 'dirproj.user'));

        $this->client->getContainer()->get(SyncDirectoryHandler::class)(new SyncDirectory());
        $groupId = $this->lldap->groupId('dirproj-GA');
        $this->lldap->groups[$groupId]['members'][] = 'stale.user';

        $crawler = $this->client->request('GET', $url);
        self::assertSame('à jour', $this->memberStatus($crawler, 'dirproj.user'));
        self::assertSame('à retirer', $this->memberStatus($crawler, 'stale.user'));
        self::assertSame(['stale.user', 'dirproj.user'], $crawler->filter('#ldap-members tr[data-row-id]')->each(fn ($tr) => $tr->attr('data-row-id')), 'pending changes first');
    }

    public function testGroupPageForRoleGroupAndUnknownKeys(): void
    {
        $ids = $this->seedPublishedTree();
        $this->grantUserRole($ids['user'], 'ROLE_WIKI_ADMIN');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL . '/groups/ROLE_WIKI_ADMIN');
        self::assertResponseIsSuccessful();
        self::assertSame('à ajouter', $this->memberStatus($crawler, 'dirproj.user'));

        // A groupe no rule selects, a role that isn't published, an unknown computed group.
        foreach ([(string) $ids['gb'], 'ROLE_ADMIN', 'c999999999'] as $key) {
            $this->client->request('GET', self::URL . "/groups/$key");
            self::assertResponseStatusCodeSame(404);
        }
    }

    private function memberStatus(Crawler $crawler, string $uid): string
    {
        return $crawler->filter(sprintf('#ldap-members tr[data-row-id="%s"] .badge', $uid))->text();
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'ldap-page-admin', null, null, ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');
    }
}
