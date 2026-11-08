<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BSUser;
use App\Entity\LatestCreatedAccount;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An automatically created account starts with an initial password (stored in plaintext in
 * LatestCreatedAccount so the secrétariat can hand it out) and must change it before using
 * netBS. Once the user picks a password, the flag and the plaintext go away.
 */
final class InitialPasswordLifecycleTest extends WebTestCase
{
    use UserFactoryTrait;

    private const INITIAL_PASSWORD = 'initial-Passw0rd-01-01-2000';
    private const NEW_PASSWORD     = 'C0rrect-Horse-Battery-Staple!';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement("DELETE a FROM sauvabelin_latest_created_accounts a JOIN sauvabelin_netbs_users u ON u.id = a.user_id WHERE u.username LIKE 'initialpw-%'");
    }

    /** A user as DoctrineUserAccountSubscriber leaves it: flag set, plaintext stored. */
    private function newAccount(string $username, array $roles = ['ROLE_USER'], ?string $email = null): BSUser
    {
        $user = $this->persistUser($this->client, $username, $email, self::INITIAL_PASSWORD, $roles);
        $user->setNewPasswordRequired(true);
        $latest = new LatestCreatedAccount();
        $latest->setUser($user);
        $latest->setPassword(self::INITIAL_PASSWORD);
        $this->em->persist($latest);
        $this->em->flush();

        return $user;
    }

    private function assertInitialPasswordCleared(string $username): void
    {
        $this->em->clear();
        $user = $this->reloadUser($this->client, $username);
        $this->assertFalse($user->isNewPasswordRequired());
        $this->assertSame([], $this->em->getRepository(LatestCreatedAccount::class)->findBy(['user' => $user]));
    }

    public function test_changing_the_password_on_my_account_clears_the_initial_password(): void
    {
        $user = $this->newAccount('initialpw-self');
        $this->client->loginUser($user, 'netbs');

        $crawler = $this->client->request('GET', '/netBS/secure/user/my-account');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="change_password"]')->form();
        $form['change_password[old_password]']         = self::INITIAL_PASSWORD;
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $this->client->submit($form);
        $this->assertResponseRedirects();

        $this->assertInitialPasswordCleared('initialpw-self');

        $this->client->request('GET', '/netBS/');
        $this->assertResponseIsSuccessful();
    }

    public function test_a_password_reset_clears_the_initial_password(): void
    {
        // A new member who never received (or lost) the initial password resets it by email.
        $this->client->enableProfiler();
        $user = $this->newAccount('initialpw-reset', email: 'initialpw-reset@example.test');

        $token = $this->requestResetAndExtractToken($this->client, $user->getUsername());
        $this->client->request('GET', '/netBS/secure/reset-password/' . $token);
        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form[name="change_password"]')->form();
        $form['change_password[new_password][first]']  = self::NEW_PASSWORD;
        $form['change_password[new_password][second]'] = self::NEW_PASSWORD;
        $this->client->submit($form);
        $this->assertResponseRedirects('/netBS/secure/login');

        $this->assertInitialPasswordCleared('initialpw-reset');
    }

    /** @dataProvider forceChange */
    public function test_an_admin_password_change_deletes_the_plaintext_initial_password(bool $force): void
    {
        $target = $this->newAccount('initialpw-admin-target');
        $this->client->loginUser($this->persistUser($this->client, 'initialpw-admin-actor', roles: ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');

        $crawler = $this->client->request('GET', '/netBS/bs/user/user/admin-change-password/' . $target->getId());
        $form = $crawler->filter('form[name="admin_change_password"]')->form();
        $form['admin_change_password[password][first]']  = self::NEW_PASSWORD;
        $form['admin_change_password[password][second]'] = self::NEW_PASSWORD;
        if ($force) {
            $form['admin_change_password[forceChange]']->tick();
        } else {
            $form['admin_change_password[forceChange]']->untick();
        }
        $this->client->submit($form);

        $this->em->clear();
        $fresh = $this->reloadUser($this->client, 'initialpw-admin-target');
        $this->assertSame([], $this->em->getRepository(LatestCreatedAccount::class)->findBy(['user' => $fresh]));
        // The admin decides whether the user still has to pick their own password.
        $this->assertSame($force, $fresh->isNewPasswordRequired());
    }

    public function forceChange(): array
    {
        return ['forced' => [true], 'not forced' => [false]];
    }

    public function test_a_user_who_must_change_the_password_is_sent_to_my_account(): void
    {
        $this->client->loginUser($this->newAccount('initialpw-forced'), 'netbs');

        $this->client->request('GET', '/netBS/');
        $this->assertResponseRedirects('/netBS/secure/user/my-account');

        $this->client->request('GET', '/netBS/secure/user/my-account');
        $this->assertResponseIsSuccessful();
    }

    public function test_an_admin_is_not_forced_to_change_the_password(): void
    {
        $this->client->loginUser($this->newAccount('initialpw-admin', ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');

        $this->client->request('GET', '/netBS/');
        $this->assertResponseIsSuccessful();
    }
}
