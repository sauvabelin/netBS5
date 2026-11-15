<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Proves the StrongPassword policy is actually enforced *through the form* at
 * every entry point — not just on the model. StrongPasswordPolicyTest checks the
 * constraint object itself; these tests submit a weak password to the real forms
 * and assert the change is refused, catching a regression that dropped the
 * constraint from a model/form or flipped its validation groups.
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

    public function test_my_account_form_rejects_a_weak_password(): void
    {
        $client = static::createClient();
        $user = $this->persistUser($client, 'weak-self-user', plainPassword: self::OLD, roles: ['ROLE_USER']);
        $client->loginUser($user, 'netbs');

        $crawler = $client->request('GET', '/netBS/secure/user/my-account');
        $form = $crawler->filter('form[name="change_password"]')->form();
        $form['change_password[old_password]']         = self::OLD;
        $form['change_password[new_password][first]']  = self::WEAK;
        $form['change_password[new_password][second]'] = self::WEAK;
        $client->submit($form);

        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $fresh  = $this->reloadUser($client, $user->getUsername());
        $this->assertTrue($hasher->isPasswordValid($fresh, self::OLD), 'the existing password must remain in place');
        $this->assertNull($fresh->getPasswordChangedAt(), 'a rejected weak password must not stamp the watermark');
    }

    public function test_admin_form_rejects_a_weak_password(): void
    {
        $client = static::createClient();
        $admin  = $this->persistUser($client, 'weak-admin-actor', plainPassword: self::OLD, roles: ['ROLE_USER', 'ROLE_ADMIN']);
        $target = $this->persistUser($client, 'weak-admin-target', plainPassword: self::OLD);
        $client->loginUser($admin, 'netbs');

        $crawler = $client->request('GET', '/netBS/bs/user/user/admin-change-password/' . $target->getId());
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="admin_change_password"]')->form();
        $form['admin_change_password[password][first]']  = self::WEAK;
        $form['admin_change_password[password][second]'] = self::WEAK;
        $client->submit($form);

        $this->assertNull(
            $this->reloadUser($client, $target->getUsername())->getPasswordChangedAt(),
            'a rejected weak password must not stamp the watermark on the target',
        );
    }
}
