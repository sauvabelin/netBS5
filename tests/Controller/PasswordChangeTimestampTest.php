<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The session-invalidation feature only logs out other sessions when
 * passwordChangedAt is stamped. The reset flow is covered separately; this
 * locks the other two password-change entry points (self-service + admin).
 */
class PasswordChangeTimestampTest extends WebTestCase
{
    use UserFactoryTrait;

    private const OLD_PASSWORD = 'Old-Passw0rd-To-Replace!';
    private const NEW_PASSWORD = 'C0rrect-Horse-Battery-Staple!';

    public function test_my_account_change_stamps_password_changed_at(): void
    {
        $client = static::createClient();
        $user = $this->persistUser($client, 'pwchange-self', plainPassword: self::OLD_PASSWORD, roles: ['ROLE_USER']);
        $client->loginUser($user, "netbs");

        $crawler = $client->request('GET', '/netBS/secure/user/my-account');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="change_password"]')->form();
        $form['change_password[old_password]']         = self::OLD_PASSWORD;
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertNotNull(
            $this->reloadUser($client, $user->getUsername())->getPasswordChangedAt(),
            'a self-service password change must stamp passwordChangedAt',
        );
    }

    public function test_admin_change_stamps_password_changed_at(): void
    {
        $client = static::createClient();
        // The admin route is ROLE_ADMIN-gated; ROLE_USER lets the actor through
        // the ^/netBS firewall access_control too (no role_hierarchy is defined).
        $actor  = $this->persistUser($client, 'pwchange-admin-actor', plainPassword: self::OLD_PASSWORD, roles: ['ROLE_USER', 'ROLE_ADMIN']);
        $target = $this->persistUser($client, 'pwchange-admin-target', plainPassword: self::OLD_PASSWORD);
        $client->loginUser($actor, "netbs");

        $crawler = $client->request('GET', '/netBS/bs/user/user/admin-change-password/' . $target->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="admin_change_password"]')->form();
        $form['admin_change_password[password][first]']  = self::NEW_PASSWORD;
        $form['admin_change_password[password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);

        $this->assertNotNull(
            $this->reloadUser($client, $target->getUsername())->getPasswordChangedAt(),
            'an admin password change must stamp passwordChangedAt on the target user',
        );
    }

    public function test_my_account_rejects_a_wrong_current_password(): void
    {
        $client = static::createClient();
        $user = $this->persistUser($client, 'pwchange-wrongcurrent', plainPassword: self::OLD_PASSWORD, roles: ['ROLE_USER']);
        $client->loginUser($user, "netbs");

        $crawler = $client->request('GET', '/netBS/secure/user/my-account');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="change_password"]')->form();
        $form['change_password[old_password]']         = 'not-the-current-password';
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);

        // The UserPassword constraint (current_password validation group) must
        // reject the change: old password stays in place, no watermark stamped.
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);
        $fresh  = $this->reloadUser($client, $user->getUsername());
        $this->assertTrue(
            $hasher->isPasswordValid($fresh, self::OLD_PASSWORD),
            'a wrong current password must leave the existing password untouched',
        );
        $this->assertNull(
            $fresh->getPasswordChangedAt(),
            'a rejected password change must not stamp passwordChangedAt',
        );
    }
}
