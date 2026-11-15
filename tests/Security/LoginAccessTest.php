<?php

namespace App\Tests\Security;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A deactivated account cannot sign in, and loses a session it already had.
 */
class LoginAccessTest extends WebTestCase
{
    use UserFactoryTrait;

    private const PASSWORD = 'C0rrect-horse-battery';

    public function test_an_inactive_account_cannot_sign_in(): void
    {
        $client = static::createClient();
        $this->persistUser($client, 'login-inactive', 'login-inactive@example.test', self::PASSWORD, ['ROLE_USER'], active: false);

        $this->passwordLogin($client, 'login-inactive', self::PASSWORD);

        $this->assertResponseRedirects('/netBS/secure/login');
        $this->assertNull($client->getContainer()->get('security.token_storage')->getToken());
    }

    public function test_deactivating_an_account_ends_its_open_session(): void
    {
        $client = static::createClient();
        $this->persistUser($client, 'login-deactivated', 'login-deactivated@example.test', self::PASSWORD, ['ROLE_USER']);
        $this->passwordLogin($client, 'login-deactivated', self::PASSWORD);
        $client->request('GET', '/netBS/');
        $this->assertResponseIsSuccessful();

        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->reloadUser($client, 'login-deactivated')->setIsActive(false);
        $em->flush();

        $client->request('GET', '/netBS/');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/secure/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
