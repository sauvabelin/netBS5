<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** User, autorisation and audit administration is ROLE_ADMIN only. */
final class SecureAdminPagesTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private BSUser $target;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->target = $this->persistUser($this->client, 'secure-admin-target', email: 'secure-target@example.test', roles: ['ROLE_USER']);
    }

    protected function tearDown(): void
    {
        $this->client->getContainer()->get('doctrine.orm.entity_manager')->getConnection()
            ->executeStatement("DELETE FROM sauvabelin_netbs_users WHERE username LIKE 'secure-admin-%'");
        parent::tearDown();
    }

    private function loginAs(string $role): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'secure-admin-' . strtolower($role), roles: [$role]), 'netbs');
    }

    /** @dataProvider adminPages */
    public function test_sg_is_refused_admin_pages(string $method, string $path): void
    {
        $this->loginAs('ROLE_SG');

        $this->client->request($method, str_replace('{id}', (string) $this->target->getId(), $path));

        $this->assertResponseStatusCodeSame(403);
    }

    public static function adminPages(): iterable
    {
        yield ['GET', '/netBS/secure/user/list'];
        yield ['GET', '/netBS/secure/user/add'];
        yield ['GET', '/netBS/secure/user/edit/{id}'];
        yield ['POST', '/netBS/secure/user/delete/{id}'];
        yield ['GET', '/netBS/secure/autorisation/list'];
        yield ['GET', '/netBS/secure/autorisation/add'];
        yield ['POST', '/netBS/secure/autorisation/delete/1'];
        yield ['GET', '/netBS/secure/utilisateurs/audit'];
        yield ['GET', '/netBS/secure/utilisateurs/audit/users/{id}'];
        yield ['GET', '/netBS/secure/utilisateurs/audit/scopes/groupe/1'];
        yield ['GET', '/netBS/secure/utilisateurs/audit/scopes/role/ROLE_SG'];
    }

    public function test_sg_delete_leaves_the_user_in_place(): void
    {
        $this->loginAs('ROLE_SG');

        $this->client->request('POST', '/netBS/secure/user/delete/' . $this->target->getId());

        $this->assertNotNull($this->reloadUser($this->client, 'secure-admin-target'));
    }

    public function test_duplicate_email_is_a_form_error(): void
    {
        $this->persistUser($this->client, 'secure-admin-other', email: 'secure-other@example.test');
        $this->loginAs('ROLE_ADMIN');

        $crawler = $this->client->request('GET', '/netBS/secure/user/edit/' . $this->target->getId());
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[email]'] = 'secure-other@example.test';
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->client->getContainer()->get('doctrine.orm.entity_manager')->clear();
        $this->assertSame('secure-target@example.test', $this->reloadUser($this->client, 'secure-admin-target')->getEmail());
    }

    public function test_unknown_user_is_404(): void
    {
        $this->loginAs('ROLE_ADMIN');

        $this->client->request('POST', '/netBS/secure/user/delete/999999999');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/netBS/secure/user/edit/999999999');
        $this->assertResponseStatusCodeSame(404);
    }

    /** switch_user needs ROLE_ALLOWED_TO_SWITCH, which no role grants today (admins included). */
    public function test_non_admin_cannot_switch_user(): void
    {
        $this->loginAs('ROLE_SG');
        $this->client->request('GET', '/netBS/secure/user/my-account?_switch_user=secure-admin-target');

        $this->assertResponseStatusCodeSame(403);
    }
}
