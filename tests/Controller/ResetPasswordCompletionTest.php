<?php

namespace App\Tests\Controller;

use App\Entity\BSUser;
use NetBS\SecureBundle\Entity\ResetPasswordRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
    private const REQUEST_URL = '/netBS/secure/forgot-password';
    private const NEW_PASSWORD = 'C0rrect-Horse-Battery-Staple!';

    public function test_completing_reset_updates_password_and_sets_timestamp(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->createUser($client, 'reset-complete-user', 'reset-complete@example.test');

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
        $user = $this->createUser($client, 'reset-replay-user', 'reset-replay@example.test');

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
        $user = $this->createUser($client, 'reset-mailfail-user', 'reset-mailfail@example.test');

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

    private function requestResetAndExtractToken(KernelBrowser $client, string $username): string
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

    private function createUser(KernelBrowser $client, string $username, ?string $email): BSUser
    {
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

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
        $user->setPassword('placeholder-hash');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
