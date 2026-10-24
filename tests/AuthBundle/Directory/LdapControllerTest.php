<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\LldapClientInterface;
use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testPreviewShowsGroupsAndPendingChangesWithoutWriting(): void
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
        self::assertStringNotContainsString('Dir Projection', $group->text(), 'members are loaded on demand');
        self::assertStringContainsString('create group "dirproj-GA"', $crawler->filter('#ldap-pending')->text());
        self::assertSame([], $this->lldap->groups, 'the preview must not write to lldap');
    }

    public function testSyncNowRejectsBadCsrfToken(): void
    {
        $this->seedGroupeTree();
        $this->loginAdmin();

        $this->client->request('POST', self::URL . '/sync', ['_token' => 'nope']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->lldap->groups);
    }

    public function testSyncNowAppliesTheChanges(): void
    {
        $ids = $this->seedGroupeTree();
        $this->loginAdmin();
        $crawler = $this->client->request('GET', self::URL);

        $this->client->submit($crawler->filter('form#ldap-sync-now')->form());

        self::assertResponseRedirects(self::URL);
        self::assertSame(['dirproj.user'], $this->lldap->membersOf('dirproj-GA'));
    }

    public function testGroupeTypeNameIsEscaped(): void
    {
        $ids = $this->seedGroupeTree();
        $this->db->executeStatement("UPDATE netbs_fichier_groupe_types SET nom = '<b>x</b>' WHERE nom = 'dirproj-a'");
        $this->loginAdmin();

        $crawler = $this->client->request('GET', self::URL);

        self::assertCount(0, $crawler->filter(sprintf('#ldap-groups tr[data-row-id="%d"] b', $ids['ga'])));
    }

    public function testMembersEndpoint(): void
    {
        $ids = $this->seedGroupeTree();
        $this->grantUserRole($ids['user'], 'ROLE_WIKI_ADMIN');
        $this->loginAdmin();
        $expected = [['uid' => 'dirproj.user', 'displayName' => 'Dir Projection']];

        $this->client->request('GET', sprintf('%s/groups/%d/members', self::URL, $ids['ga']));
        self::assertSame($expected, json_decode($this->client->getResponse()->getContent(), true));

        $this->client->request('GET', self::URL . '/groups/ROLE_WIKI_ADMIN/members');
        self::assertSame($expected, json_decode($this->client->getResponse()->getContent(), true));

        // A groupe whose type doesn't create a group, or a role that isn't published.
        foreach ([(string) $ids['gb'], 'ROLE_ADMIN'] as $key) {
            $this->client->request('GET', self::URL . "/groups/$key/members");
            self::assertResponseStatusCodeSame(404);
        }
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'ldap-page-admin', null, null, ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');
    }
}
