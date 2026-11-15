<?php

namespace App\Tests\Controller;

use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use NetBS\SecureBundle\Entity\ResetPasswordRequest;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Covers the completion half of the reset flow (consume token, persist hash,
 * stamp passwordChangedAt) and the mailer-failure rollback on the request half.
 * The request/email-issuance half is covered by ResetPasswordControllerTest.
 */
class ResetPasswordCompletionTest extends WebTestCase
{
    use UserFactoryTrait;

    private const REQUEST_URL = '/netBS/secure/forgot-password';
    private const NEW_PASSWORD = 'C0rrect-Horse-Battery-Staple!';

    public function test_completing_reset_updates_password_and_sets_timestamp(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-complete-user', 'reset-complete@example.test');

        $token = $this->requestResetAndExtractToken($client, $user->getUsername());

        $client->request('GET', '/netBS/secure/reset-password/' . $token);
        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);

        $this->assertResponseRedirects('/netBS/secure/login');

        $em      = $client->getContainer()->get('doctrine.orm.entity_manager');
        $hasher  = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $fresh   = $em->getRepository(BSUser::class)->findOneBy(['username' => $user->getUsername()]);

        $this->assertTrue(
            $hasher->isPasswordValid($fresh, self::NEW_PASSWORD),
            'the stored hash must verify the newly chosen password',
        );
        $this->assertNotNull(
            $fresh->getPasswordChangedAt(),
            'passwordChangedAt must be stamped so other sessions are invalidated',
        );
    }

    public function test_token_cannot_be_replayed_after_completion(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-replay-user', 'reset-replay@example.test');

        $token = $this->requestResetAndExtractToken($client, $user->getUsername());

        // First use: succeeds.
        $client->request('GET', '/netBS/secure/reset-password/' . $token);
        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);
        $this->assertResponseRedirects('/netBS/secure/login');

        // Replay: the consumed token must be rejected, not let a second change through.
        $client->request('GET', '/netBS/secure/reset-password/' . $token);
        $client->followRedirect();   // -> /reset-password (reads session token)
        $client->followRedirect();   // -> /forgot-password (token invalid)
        $this->assertSelectorExists('form input[name="reset_password_request_form[username]"]');
    }

    public function test_mail_transport_failure_rolls_back_the_token(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->persistUser($client, 'reset-mailfail-user', 'reset-mailfail@example.test');

        // Force a transport failure without swapping the private mailer service:
        // a MessageEvent listener that throws surfaces out of Mailer::send() as a
        // TransportException, exactly like a real outage.
        static::getContainer()->get('event_dispatcher')->addListener(
            MessageEvent::class,
            static function (): void { throw new TransportException('smtp unavailable'); },
        );

        $crawler = $client->request('GET', self::REQUEST_URL);
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        // Generic response preserved — a transport outage must not become a 500.
        $this->assertResponseRedirects();

        // No email went out, so no live token may be left behind.
        $em    = $client->getContainer()->get('doctrine.orm.entity_manager');
        $fresh = $em->getRepository(BSUser::class)->findOneBy(['username' => $user->getUsername()]);
        $this->assertSame(
            0,
            $em->getRepository(ResetPasswordRequest::class)->count(['user' => $fresh]),
            'a committed reset token must be rolled back when the email fails to send',
        );
    }

    public function test_a_forged_token_is_rejected(): void
    {
        $client = static::createClient();

        // Syntactically valid per the route requirement ([A-Za-z0-9_\-]+) but
        // never issued: must not open the reset form.
        $client->request('GET', '/netBS/secure/reset-password/' . str_repeat('a', 40));
        $client->followRedirect();   // -> /reset-password (stores token in session)
        $client->followRedirect();   // -> /forgot-password (token does not resolve)

        $this->assertSelectorExists('form input[name="reset_password_request_form[username]"]');
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'reset-expired-user', 'reset-expired@example.test');

        $token = $this->requestResetAndExtractToken($client, $user->getUsername());

        // Move the bundle's clock past the token lifetime (isExpired() compares
        // expiresAt against Clock::get()); the selector/token still resolve, so
        // this exercises the expiry branch specifically.
        $previousClock = Clock::get();
        Clock::set(new MockClock('+1 day'));
        try {
            $client->request('GET', '/netBS/secure/reset-password/' . $token);
            $client->followRedirect();   // -> /reset-password
            $client->followRedirect();   // -> /forgot-password (expired)
        } finally {
            Clock::set($previousClock);
        }

        $this->assertSelectorExists('form input[name="reset_password_request_form[username]"]');
    }
}
