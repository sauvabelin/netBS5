<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Proves the StrongPassword policy is actually enforced *through the form* at
 * every entry point — not just on the model. StrongPasswordPolicyTest checks the
 * constraint object and its attachment to the models; these tests submit a weak
 * password to the real forms and assert the change is refused, catching a
 * regression that dropped the constraint from a form or flipped its validation
 * groups (which the unit test cannot see).
 */
class WeakPasswordRejectionTest extends WebTestCase
{
    use UserFactoryTrait;

    private const WEAK        = 'aaaaaaaaaaaa';              // 12 chars, below MEDIUM strength
    private const OLD         = 'Old-Passw0rd-To-Replace!';

    public function test_reset_form_rejects_a_weak_password(): void
    {
        $client = static::createClient();
        $client->enableProfiler();
        $user = $this->persistUser($client, 'weak-reset-user', email: 'weak-reset@example.test');

        $token = $this->requestResetAndExtractToken($client, $user->getUsername());

        $client->request('GET', '/netBS/secure/reset-password/' . $token);
        $crawler = $client->followRedirect();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['change_password[new_password][first]']  = self::WEAK;
        $form['change_password[new_password][second]'] = self::WEAK;
        $client->submit($form);

        // Success would 302 to /login and stamp passwordChangedAt. Rejection
        // re-renders the form; the InvalidFormStatusListener bumps that reply to
        // 422 (Turbo re-render), so 422 — not a redirect — is the refusal signal.
        $this->assertResponseStatusCodeSame(422, 'a weak password must be refused and the reset form re-rendered');
        $this->assertNull(
            $this->reloadUser($client, $user->getUsername())->getPasswordChangedAt(),
            'a rejected weak password must not complete the reset',
        );
    }

    private function requestResetAndExtractToken(KernelBrowser $client, string $username): string
    {
        $crawler = $client->request('GET', '/netBS/secure/forgot-password');
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $username;
        $client->submit($form);

        $this->assertEmailCount(1);
        $body = $this->getMailerMessage(0)->getHtmlBody();
        $this->assertSame(1, preg_match('#/reset-password/([A-Za-z0-9_\-]+)#', $body, $m), 'email must contain a reset link');

        return $m[1];
    }
}
