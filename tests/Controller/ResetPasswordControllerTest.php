<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Anti-enumeration contract: every submission of the request form — known,
 * unknown or inactive user — gets the same redirect to the static check-email
 * page (which renders no request-specific data); only the email differs.
 */
class ResetPasswordControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private const REQUEST_URL = '/netBS/secure/forgot-password';
    private const CHECK_EMAIL_URL = '/netBS/secure/forgot-password/check-email';

    public function test_request_unknown_username_shows_generic_response_and_sends_no_email(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = 'no-such-user';
        $client->submit($form);

        $this->assertResponseRedirects(self::CHECK_EMAIL_URL);
        $this->assertEmailCount(0);
    }

    public function test_request_known_user_with_email_sends_one_email(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-test-alice', 'reset-test-alice@example.test');

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        // Assert on the POST response's profile, before follow-redirect would
        // swap to the (email-less) GET /check-email profile.
        $this->assertResponseRedirects(self::CHECK_EMAIL_URL);
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', 'reset-test-alice@example.test');
        $this->assertEmailHtmlBodyContains($email, '/reset-password/');
        $this->assertEmailTextBodyContains($email, '/reset-password/');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function test_request_by_renamed_login_handle_sends_one_email(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-test-handle', 'reset-test-handle@example.test');
        $user->setLoginUsername('reset-test-handle.renamed');
        $client->getContainer()->get('doctrine.orm.entity_manager')->flush();

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = 'reset-test-handle.renamed';
        $client->submit($form);

        $this->assertResponseRedirects(self::CHECK_EMAIL_URL);
        $this->assertEmailCount(1);
    }

    public function test_resubmit_issues_a_fresh_email_and_invalidates_the_old_token(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-test-resubmit', 'reset-test-resubmit@example.test');

        // Re-submit issues a brand-new link (GitHub-style; anti-abuse is the
        // per-IP limiter, not a per-user throttle). Each request emits exactly
        // one email, and the two tokens must differ.
        $token1 = $this->requestResetAndExtractToken($client, $user->getUsername());
        $token2 = $this->requestResetAndExtractToken($client, $user->getUsername());
        $this->assertNotSame($token1, $token2, 're-submit must mint a fresh token');

        // The old token must no longer resolve: using it bounces back to the
        // request form instead of opening the reset form. (Without this, "old
        // token invalidated" was only asserted by the test's name.)
        $client->request('GET', '/netBS/secure/reset-password/' . $token1);
        $client->followRedirect();   // -> /reset-password (reads session token)
        $client->followRedirect();   // -> /forgot-password (token invalid)
        $this->assertSelectorExists('form input[name="reset_password_request_form[username]"]');
    }

    public function test_request_inactive_user_sends_no_email(): void
    {
        $client = static::createClient();
        $user = $this->persistUser($client, 'reset-test-inactive', 'reset-test-inactive@example.test', active: false);

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        $this->assertResponseRedirects(self::CHECK_EMAIL_URL);
        $this->assertEmailCount(0);
    }
}
