<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the main netBS login screen: the markup contract that lets password
 * managers and browser autofill recognise the form, and the authentication
 * behaviour the template is wired to (successful login, and a failed login
 * showing an error while re-populating the username).
 */
class LoginControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private const LOGIN_URL = '/netBS/secure/login';
    private const PASSWORD = 'C0rrect-horse-battery';

    /**
     * The markup contract password managers and autofill rely on: an identified
     * username/password pair and an explicit submit button.
     */
    public function test_login_form_markup_supports_password_managers(): void
    {
        $client = static::createClient();
        $client->request('GET', self::LOGIN_URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"][autocomplete="username"]');
        $this->assertSelectorExists('input[name="_password"][autocomplete="current-password"]');
        $this->assertSelectorExists('label[for="username"]');
        $this->assertSelectorExists('label[for="password"]');
        $this->assertSelectorExists('form button[type="submit"]');
    }

    public function test_valid_credentials_authenticate_and_redirect_off_the_login_page(): void
    {
        $client = static::createClient();
        $this->persistUser($client, 'login-test-ok', 'login-ok@example.test', self::PASSWORD, ['ROLE_USER']);

        $crawler = $client->request('GET', self::LOGIN_URL);
        $form = $crawler->selectButton('Connexion')->form();
        $form['_username'] = 'login-test-ok';
        $form['_password'] = self::PASSWORD;
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertStringNotContainsString(
            '/secure/login',
            (string) $client->getResponse()->headers->get('Location'),
            'a successful login must not bounce back to the login page'
        );
    }

    public function test_failed_login_shows_an_error_and_repopulates_the_username(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', self::LOGIN_URL);
        $form = $crawler->selectButton('Connexion')->form();
        $form['_username'] = 'wrong-user';
        $form['_password'] = 'definitely-the-wrong-password';
        $client->submit($form);

        $this->assertResponseRedirects();
        $crawler = $client->followRedirect();
        $this->assertStringContainsString('/secure/login', $client->getRequest()->getUri());
        $this->assertSelectorExists('.text-danger');
        $this->assertSame(
            'wrong-user',
            $crawler->filter('input[name="_username"]')->attr('value'),
            'the login form must re-populate the username after a failed attempt'
        );
    }
}
