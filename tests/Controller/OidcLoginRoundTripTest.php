<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * A login started by an OIDC client (wiki, Nextcloud…) must return to that
 * client even when the session is lost while the login form is open: the
 * `login_challenge` travels in the login URL, not only in the session.
 */
class OidcLoginRoundTripTest extends WebTestCase
{
    use UserFactoryTrait;

    private const CHALLENGE = 'ch-123_abc=';
    private const PASSWORD = 'C0rrect-horse-battery';

    private function createOidcClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        // The login page asks Hydra for the client's branding; unknown challenge → plain form.
        static::getContainer()->set('hydra.admin.client', new MockHttpClient(
            fn () => new MockResponse('{"error":"not_found"}', ['http_code' => 404]),
        ));

        return $client;
    }

    private function submitLogin(KernelBrowser $client, string $username): void
    {
        $form = $client->getCrawler()->selectButton('Connexion')->form();
        $form['_username'] = $username;
        $form['_password'] = self::PASSWORD;
        $client->submit($form);
    }

    public function test_unauthenticated_oidc_login_redirects_with_the_challenge(): void
    {
        $client = $this->createOidcClient();

        $client->request('GET', '/oidc-login', ['login_challenge' => self::CHALLENGE]);

        $this->assertResponseRedirects('/netBS/secure/login?login_challenge=' . urlencode(self::CHALLENGE));
    }

    public function test_login_page_with_a_challenge_returns_to_the_oidc_flow(): void
    {
        $client = $this->createOidcClient();
        $this->persistUser($client, 'oidc-rt-ok', 'oidc-rt-ok@example.test', self::PASSWORD, ['ROLE_USER']);

        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);
        $this->submitLogin($client, 'oidc-rt-ok');

        $this->assertResponseRedirects('/oidc-login?login_challenge=' . urlencode(self::CHALLENGE));
    }

    public function test_lost_session_keeps_the_challenge(): void
    {
        $client = $this->createOidcClient();
        $this->persistUser($client, 'oidc-rt-lost', 'oidc-rt-lost@example.test', self::PASSWORD, ['ROLE_USER']);

        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);
        // Session expired (or garbage-collected) while the form was open.
        $client->getCookieJar()->clear();
        $this->submitLogin($client, 'oidc-rt-lost');

        $this->assertResponseRedirects('/netBS/secure/login?login_challenge=' . urlencode(self::CHALLENGE));
        $client->followRedirect();

        $this->submitLogin($client, 'oidc-rt-lost');
        $this->assertResponseRedirects('/oidc-login?login_challenge=' . urlencode(self::CHALLENGE));
    }

    public function test_signed_in_user_on_the_login_page_continues_the_oidc_flow(): void
    {
        $client = $this->createOidcClient();
        $this->persistUser($client, 'oidc-rt-signed', 'oidc-rt-signed@example.test', self::PASSWORD, ['ROLE_USER']);
        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);
        $this->submitLogin($client, 'oidc-rt-signed');

        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);

        $this->assertResponseRedirects('/oidc-login?login_challenge=' . urlencode(self::CHALLENGE));
    }

    public function test_remembered_user_on_the_login_page_is_asked_for_the_password(): void
    {
        // /oidc-login needs a full login; sending a remember-me session there
        // bounces straight back to this page, in a loop.
        $client = $this->createOidcClient();
        $this->persistUser($client, 'oidc-rt-remember', 'oidc-rt-remember@example.test', self::PASSWORD, ['ROLE_USER']);
        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);
        $form = $client->getCrawler()->selectButton('Connexion')->form();
        $form['_username'] = 'oidc-rt-remember';
        $form['_password'] = self::PASSWORD;
        $form['_remember_me']->tick();
        $client->submit($form);
        // Session gone, remember-me cookie left.
        $remember = $client->getCookieJar()->get('REMEMBERME', '/netBS');
        $this->assertNotNull($remember);
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($remember);

        $client->request('GET', '/netBS/secure/login', ['login_challenge' => self::CHALLENGE]);

        $this->assertResponseIsSuccessful();
    }

    public function test_a_malformed_challenge_is_ignored(): void
    {
        $client = $this->createOidcClient();

        $client->request('GET', '/netBS/secure/login', ['login_challenge' => 'x"><script>']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/netBS/secure/login"]');
    }
}
