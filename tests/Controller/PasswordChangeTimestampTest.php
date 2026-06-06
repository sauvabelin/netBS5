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
        $user = $this->createUser($client, 'pwchange-self', self::OLD_PASSWORD, withRole: true);
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
        $actor  = $this->createUser($client, 'pwchange-admin-actor', self::OLD_PASSWORD, withRole: true);
        $target = $this->createUser($client, 'pwchange-admin-target', self::OLD_PASSWORD, withRole: false);
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

    private function reload(KernelBrowser $client, BSUser $user): BSUser
    {
        return $client->getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(BSUser::class)
            ->findOneBy(['username' => $user->getUsername()]);
    }

    private function createUser(KernelBrowser $client, string $username, string $plain, bool $withRole): BSUser
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
        if ($withRole) {
            $user->addRole($em->getRepository(Role::class)->findOneBy(['role' => 'ROLE_USER']));
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
