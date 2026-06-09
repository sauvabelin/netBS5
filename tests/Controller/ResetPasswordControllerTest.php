<?php

namespace App\Tests\Controller;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ResetPasswordControllerTest extends WebTestCase
{
    private const REQUEST_URL = '/netBS/secure/forgot-password';
    private const GENERIC_BANNER = 'Si un compte correspond';

    public function test_request_form_renders(): void
    {
        $client = static::createClient();
        $client->request('GET', self::REQUEST_URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form input[name="reset_password_request_form[username]"]');
    }

    public function test_request_unknown_username_shows_generic_response_and_sends_no_email(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = 'no-such-user';
        $client->submit($form);

        $client->followRedirect();
        $this->assertSelectorTextContains('body', self::GENERIC_BANNER);
        $this->assertEmailCount(0);
    }

    public function test_request_known_user_with_email_sends_one_email(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->createUser($client, 'reset-test-alice', 'reset-test-alice@example.test');

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        // Assert on the POST response's profile, before follow-redirect would
        // swap to the (email-less) GET /check-email profile.
        $this->assertResponseRedirects();
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertEmailAddressContains($email, 'To', 'reset-test-alice@example.test');
        $this->assertEmailHtmlBodyContains($email, '/reset-password/');

        // Sanity-check the follow-through page renders the generic banner.
        $client->followRedirect();
        $this->assertSelectorTextContains('body', self::GENERIC_BANNER);
    }

    public function test_request_known_user_without_email_sends_no_email(): void
    {
        $client = static::createClient();
        $user = $this->createUser($client, 'reset-test-noemail', null);

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        $client->followRedirect();
        $this->assertSelectorTextContains('body', self::GENERIC_BANNER);
        $this->assertEmailCount(0);
    }

    public function test_resubmit_issues_a_fresh_email_and_invalidates_the_old_token(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->createUser($client, 'reset-test-resubmit', 'reset-test-resubmit@example.test');

        // Re-submit issues a brand-new link (GitHub-style; anti-abuse is the
        // per-IP limiter, not a per-user throttle). Each request emits exactly
        // one email, and the two tokens must differ.
        $token1 = $this->requestAndExtractToken($client, $user->getUsername());
        $token2 = $this->requestAndExtractToken($client, $user->getUsername());
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
        $user = $this->createUser($client, 'reset-test-inactive', 'reset-test-inactive@example.test', active: false);

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        $client->followRedirect();
        $this->assertSelectorTextContains('body', self::GENERIC_BANNER);
        $this->assertEmailCount(0);
    }

    private function requestAndExtractToken(KernelBrowser $client, string $username): string
    {
        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $username;
        $client->submit($form);

        $this->assertEmailCount(1);
        $body = $this->getMailerMessage(0)->getHtmlBody();
        $this->assertSame(1, preg_match('#/reset-password/([A-Za-z0-9_\-]+)#', $body, $m), 'email must contain a reset link');

        return $m[1];
    }

    private function createUser(KernelBrowser $client, string $username, ?string $email, bool $active = true): BSUser
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        // Hard-delete any leftover from a prior run; the soft-delete filter would
        // otherwise hide the row and we'd hit a UNIQUE(username) violation on insert.
        $em->getFilters()->disable('softdeleteable');
        $em->createQuery('DELETE FROM App\\Entity\\BSUser u WHERE u.username = :u')
            ->setParameter('u', $username)
            ->execute();
        if ($email !== null) {
            $em->createQuery('DELETE FROM App\\Entity\\BSUser u WHERE u.email = :e')
                ->setParameter('e', $email)
                ->execute();
        }
        $em->getFilters()->enable('softdeleteable');

        $user = new BSUser();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setIsActive($active);
        $user->setPassword('placeholder-hash');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
