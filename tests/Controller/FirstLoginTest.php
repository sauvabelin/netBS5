<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BSUser;
use App\Entity\LatestCreatedAccount;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * An account with a temporary password may do one thing: choose its own password on the
 * first-login page. Then it continues to where it was going, a service's sign-in included.
 */
final class FirstLoginTest extends WebTestCase
{
    use UserFactoryTrait;

    private const URL              = '/netBS/secure/first-login';
    private const INITIAL_PASSWORD = 'initial-Passw0rd-01-01-2000';
    private const NEW_PASSWORD     = 'C0rrect-Horse-Battery-Staple!';
    private const CHALLENGE        = 'first-login-challenge_123';

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
        $this->em->getConnection()->executeStatement("DELETE a FROM sauvabelin_latest_created_accounts a JOIN sauvabelin_netbs_users u ON u.id = a.user_id WHERE u.username LIKE 'firstlogin-%'");
    }

    private function flaggedUser(string $username, array $roles = ['ROLE_USER'], ?string $email = null): BSUser
    {
        $user = $this->persistUser($this->client, $username, $email, self::INITIAL_PASSWORD, $roles);
        $user->setNewPasswordRequired(true);
        $latest = new LatestCreatedAccount();
        $latest->setUser($user);
        $latest->setPassword(self::INITIAL_PASSWORD);
        $this->em->persist($latest);
        $this->em->flush();
        $this->passwordLogin($this->client, $username, self::INITIAL_PASSWORD);

        return $user;
    }

    private function submitFirstLogin(string $password, ?string $email = null): void
    {
        $crawler = $this->client->request('GET', self::URL);
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="first_login"]')->form();
        $form['first_login[new_password][first]']  = $password;
        $form['first_login[new_password][second]'] = $password;
        if ($email !== null) {
            $form['first_login[email]'] = $email;
        }
        $this->client->submit($form);
    }

    private function reload(string $username): BSUser
    {
        $this->em->clear();

        return $this->reloadUser($this->client, $username);
    }

    /** @dataProvider blockedPages */
    public function test_a_temporary_password_only_leads_to_the_first_login_page(array $roles, string $path): void
    {
        $this->flaggedUser('firstlogin-blocked', $roles);

        $this->client->request('GET', $path);

        $this->assertResponseRedirects(self::URL);
    }

    public function blockedPages(): array
    {
        return [
            'dashboard'                => [['ROLE_USER'], '/netBS/'],
            'my account'               => [['ROLE_USER'], '/netBS/secure/user/my-account'],
            'admin page, admin user'   => [['ROLE_USER', 'ROLE_ADMIN'], '/netBS/secure/user/list'],
            'trash, sg user'           => [['ROLE_USER', 'ROLE_SG'], '/netBS/trash/list'],
            'a service sign-in'        => [['ROLE_USER'], '/oidc-login?login_challenge=' . self::CHALLENGE],
        ];
    }

    public function test_a_post_elsewhere_is_refused(): void
    {
        $user = $this->flaggedUser('firstlogin-post', ['ROLE_USER', 'ROLE_ADMIN']);

        $this->client->request('POST', '/netBS/secure/user/delete/' . $user->getId());

        $this->assertNotNull($this->reload('firstlogin-post'));
        $this->assertFalse($this->client->getResponse()->isSuccessful());
    }

    public function test_choosing_a_password_restores_the_account_and_continues_to_the_service(): void
    {
        $this->flaggedUser('firstlogin-oidc');
        $this->client->request('GET', '/oidc-login?login_challenge=' . self::CHALLENGE);

        $this->submitFirstLogin(self::NEW_PASSWORD);

        $this->assertResponseRedirects('/oidc-login?login_challenge=' . self::CHALLENGE);
        $user = $this->reload('firstlogin-oidc');
        $this->assertFalse($user->isNewPasswordRequired());
        $this->assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::NEW_PASSWORD));
        $this->assertSame([], $this->em->getRepository(LatestCreatedAccount::class)->findBy(['user' => $user]));
    }

    public function test_without_a_destination_it_continues_to_the_dashboard_still_logged_in(): void
    {
        $this->flaggedUser('firstlogin-direct');

        $this->submitFirstLogin(self::NEW_PASSWORD);
        $this->assertResponseRedirects('/netBS/');

        $this->client->followRedirect();
        $this->client->request('GET', '/netBS/');
        $this->assertResponseIsSuccessful();
    }

    public function test_the_email_is_optional_and_stored_when_given(): void
    {
        $this->flaggedUser('firstlogin-email');

        $this->submitFirstLogin(self::NEW_PASSWORD, 'firstlogin-email@example.test');

        $this->assertResponseRedirects();
        $this->assertSame('firstlogin-email@example.test', $this->reload('firstlogin-email')->getEmail());
    }

    public function test_an_email_used_by_another_account_saves_nothing(): void
    {
        $this->persistUser($this->client, 'firstlogin-email-owner', 'firstlogin-taken@example.test');
        $this->flaggedUser('firstlogin-email-dup');

        $this->submitFirstLogin(self::NEW_PASSWORD, 'firstlogin-taken@example.test');

        $this->assertResponseStatusCodeSame(422);
        $user = $this->reload('firstlogin-email-dup');
        $this->assertTrue($user->isNewPasswordRequired());
        $this->assertNull($user->getEmail());
        $this->assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, self::INITIAL_PASSWORD));
    }

    public function test_an_account_without_a_temporary_password_is_sent_on(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'firstlogin-normal', roles: ['ROLE_USER']), 'netbs');

        $this->client->request('GET', self::URL);

        $this->assertResponseRedirects('/netBS/');
    }
}
