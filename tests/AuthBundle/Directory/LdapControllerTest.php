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
        $ids = $this->seedGroupeTree();
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
        self::assertSame([], $this->lldap->groups, 'the page must not write to lldap');
    }

    public function testGroupeTypeNameIsEscaped(): void
    {
        $ids = $this->seedGroupeTree();
        $this->db->executeStatement("UPDATE netbs_fichier_groupe_types SET nom = '<b>x</b>' WHERE nom = 'dirproj-a'");
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertCount(0, $crawler->filter(sprintf('#ldap-groups tr[data-row-id="%d"] b', $ids['ga'])));
    }

    public function testGroupPageComparesMembersWithLldap(): void
    {
        $ids = $this->seedGroupeTree();
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
        $ids = $this->seedGroupeTree();
        $this->grantUserRole($ids['user'], 'ROLE_WIKI_ADMIN');
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL . '/groups/ROLE_WIKI_ADMIN');
        self::assertResponseIsSuccessful();
        self::assertSame('à ajouter', $this->memberStatus($crawler, 'dirproj.user'));

        // A groupe whose type doesn't create a group, or a role that isn't published.
        foreach ([(string) $ids['gb'], 'ROLE_ADMIN'] as $key) {
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
