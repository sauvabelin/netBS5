<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\BSUser;
use App\Security\BSUserProvider;
use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The permanent username identifies the account behind the scenes (tokens,
 * OIDC sub, LDAP uid); only the editable login username is typed to sign in.
 * Both share one namespace, so a string never names two users.
 */
final class BSUserProviderTest extends WebTestCase
{
    use UserFactoryTrait;

    private const PASSWORD = 'C0rrect-horse-battery';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testOnlyTheLoginUsernameSignsIn(): void
    {
        $this->renamedUser('handle-alice', 'handle-alice.new');

        $this->passwordLogin($this->client, 'handle-alice', self::PASSWORD);
        $this->assertResponseRedirects('/netBS/secure/login');

        $this->passwordLogin($this->client, 'handle-alice.new', self::PASSWORD);
        $this->client->request('GET', '/netBS/');
        $this->assertResponseIsSuccessful();
    }

    // Issuing a token needs JWT keys the test env lacks, so only the refusal is checked.
    public function testThePermanentUsernameGetsNoApiToken(): void
    {
        $this->renamedUser('handle-alice', 'handle-alice.new');

        $this->requestToken('handle-alice');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testTokensResolveByThePermanentUsernameOnly(): void
    {
        $alice = $this->renamedUser('handle-alice', 'handle-alice.new');
        $provider = static::getContainer()->get(BSUserProvider::class);

        self::assertSame($alice->getId(), $provider->loadUserByIdentifier('handle-alice')->getId());

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('handle-alice.new');
    }

    public function testOnlyTheLoginUsernameRequestsAPasswordReset(): void
    {
        $this->renamedUser('handle-alice', 'handle-alice.new', 'handle-alice@example.test');
        $this->client->enableProfiler();

        $crawler = $this->client->request('GET', '/netBS/secure/forgot-password');
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = 'handle-alice';
        $this->client->submit($form);
        $this->assertEmailCount(0);

        $this->requestResetAndExtractToken($this->client, 'handle-alice.new');
    }

    public function testAnotherUserCannotTakeAUsernameOrHandle(): void
    {
        $this->renamedUser('handle-alice', 'handle-alice.new');
        $validator = static::getContainer()->get(ValidatorInterface::class);

        foreach (['handle-alice', 'handle-alice.new'] as $taken) {
            $bob = (new BSUser())->setLoginUsername('handle-bob');
            $bob->setLoginUsername($taken);
            self::assertCount(1, $this->handleViolations($validator, $bob), "\"$taken\" must be refused");
        }

        self::assertCount(0, $this->handleViolations($validator, (new BSUser())->setLoginUsername('handle-bob')));
    }

    private function requestToken(string $username): void
    {
        $this->client->request('POST', '/api/v1/netBS/gettoken', server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => $username, 'password' => self::PASSWORD]));
    }

    private function handleViolations(ValidatorInterface $validator, BSUser $user): array
    {
        return array_filter(
            iterator_to_array($validator->validate($user)),
            fn ($violation) => $violation->getPropertyPath() === 'loginUsername',
        );
    }

    private function renamedUser(string $username, string $handle, ?string $email = null): BSUser
    {
        $user = $this->persistUser($this->client, $username, $email, self::PASSWORD, ['ROLE_USER']);
        $user->setLoginUsername($handle);
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();
        return $user;
    }
}
