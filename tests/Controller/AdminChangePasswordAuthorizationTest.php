<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Locks the authorization boundary on the admin password-change endpoint
 * (src/Controller/UserController::modalAdminChangePasswordAction). The route
 * sits under ^/netBS/bs, which the firewall only gates at ROLE_USER, so without
 * the explicit #[IsGranted('ROLE_ADMIN')] any logged-in member could change any
 * other account's password (account takeover). These tests fail loudly if that
 * guard is ever removed or the route is moved out from under it.
 */
class AdminChangePasswordAuthorizationTest extends WebTestCase
{
    use UserFactoryTrait;

    private function url(int $targetId): string
    {
        return '/netBS/bs/user/user/admin-change-password/' . $targetId;
    }

    public function test_a_plain_role_user_is_forbidden(): void
    {
        $client = static::createClient();
        $actor  = $this->persistUser($client, 'authz-plain-user', plainPassword: 'whatever', roles: ['ROLE_USER']);
        $target = $this->persistUser($client, 'authz-target', plainPassword: 'whatever');
        $client->loginUser($actor, 'netbs');

        $client->request('GET', $this->url($target->getId()));

        $this->assertResponseStatusCodeSame(403, 'a ROLE_USER must not reach the admin password-change endpoint');
    }

    public function test_an_admin_is_allowed(): void
    {
        $client = static::createClient();
        $actor  = $this->persistUser($client, 'authz-admin', plainPassword: 'whatever', roles: ['ROLE_USER', 'ROLE_ADMIN']);
        $target = $this->persistUser($client, 'authz-target', plainPassword: 'whatever');
        $client->loginUser($actor, 'netbs');

        $client->request('GET', $this->url($target->getId()));

        $this->assertResponseIsSuccessful('an admin must be able to open the password-change modal');
    }

    public function test_an_anonymous_visitor_is_denied(): void
    {
        $client = static::createClient();
        $target = $this->persistUser($client, 'authz-target', plainPassword: 'whatever');

        $client->request('GET', $this->url($target->getId()));

        // form_login firewall bounces an unauthenticated visitor to the login page.
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location') ?? '');
    }
}
