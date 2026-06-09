<?php

namespace App\Tests\Controller;

use App\Entity\BSUser;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The session-invalidation feature only logs out other sessions when
 * passwordChangedAt is stamped. The reset flow is covered separately; this
 * locks the other two password-change entry points (self-service + admin).
 */
class PasswordChangeTimestampTest extends WebTestCase
{
    private const OLD_PASSWORD = 'Old-Passw0rd-To-Replace!';
    private const NEW_PASSWORD = 'C0rrect-Horse-Battery-Staple!';

    public function test_my_account_change_stamps_password_changed_at(): void
    {
        $client = static::createClient();
        $user = $this->createUser($client, 'pwchange-self', self::OLD_PASSWORD, ['ROLE_USER']);
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
            $this->reload($client, $user)->getPasswordChangedAt(),
            'a self-service password change must stamp passwordChangedAt',
        );
    }

    public function test_admin_change_stamps_password_changed_at(): void
    {
        $client = static::createClient();
        // The admin route is ROLE_ADMIN-gated; ROLE_USER lets the actor through
        // the ^/netBS firewall access_control too (no role_hierarchy is defined).
        $actor  = $this->createUser($client, 'pwchange-admin-actor', self::OLD_PASSWORD, ['ROLE_USER', 'ROLE_ADMIN']);
        $target = $this->createUser($client, 'pwchange-admin-target', self::OLD_PASSWORD, []);
        $client->loginUser($actor, "netbs");

        $crawler = $client->request('GET', '/netBS/bs/user/user/admin-change-password/' . $target->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="admin_change_password"]')->form();
        $form['admin_change_password[password][first]']  = self::NEW_PASSWORD;
        $form['admin_change_password[password][second]'] = self::NEW_PASSWORD;
        $client->submit($form);

        $this->assertNotNull(
            $this->reload($client, $target)->getPasswordChangedAt(),
            'an admin password change must stamp passwordChangedAt on the target user',
        );
    }

    public function test_my_account_rejects_a_wrong_current_password(): void
    {
        $client = static::createClient();
        $user = $this->createUser($client, 'pwchange-wrongcurrent', self::OLD_PASSWORD, ['ROLE_USER']);
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
        $fresh  = $this->reload($client, $user);
        $this->assertTrue(
            $hasher->isPasswordValid($fresh, self::OLD_PASSWORD),
            'a wrong current password must leave the existing password untouched',
        );
        $this->assertNull(
            $fresh->getPasswordChangedAt(),
            'a rejected password change must not stamp passwordChangedAt',
        );
    }

    private function reload(KernelBrowser $client, BSUser $user): BSUser
    {
        return $client->getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(BSUser::class)
            ->findOneBy(['username' => $user->getUsername()]);
    }

    /** @param string[] $roles role names to attach, e.g. ['ROLE_USER', 'ROLE_ADMIN'] */
    private function createUser(KernelBrowser $client, string $username, string $plain, array $roles = []): BSUser
    {
        $em     = $client->getContainer()->get('doctrine.orm.entity_manager');
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $em->getFilters()->disable('softdeleteable');
        $em->createQuery('DELETE FROM App\\Entity\\BSUser u WHERE u.username = :u')
            ->setParameter('u', $username)
            ->execute();
        $em->getFilters()->enable('softdeleteable');

        $user = new BSUser();
        $user->setUsername($username);
        $user->setPassword($hasher->hashPassword($user, $plain));
        foreach ($roles as $roleName) {
            $user->addRole($em->getRepository(Role::class)->findOneBy(['role' => $roleName]));
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
