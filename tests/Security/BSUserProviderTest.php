<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\BSUser;
use App\Security\BSUserProvider;
use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Username (permanent, carried by tokens) and login username (editable, typed
 * at login) share one namespace, so either resolves to the same single user.
 */
final class BSUserProviderTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testTokensAndLoginHandleResolveToTheRenamedUser(): void
    {
        $alice = $this->renamedUser('handle-alice', 'handle-alice.new');
        $provider = static::getContainer()->get(BSUserProvider::class);

        self::assertSame($alice->getId(), $provider->loadUserByIdentifier('handle-alice')->getId(), 'token identifier');
        self::assertSame($alice->getId(), $provider->loadUserByIdentifier('handle-alice.new')->getId(), 'login handle');
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

    private function handleViolations(ValidatorInterface $validator, BSUser $user): array
    {
        return array_filter(
            iterator_to_array($validator->validate($user)),
            fn ($violation) => $violation->getPropertyPath() === 'loginUsername',
        );
    }

    private function renamedUser(string $username, string $handle): BSUser
    {
        $user = $this->persistUser($this->client, $username);
        $user->setLoginUsername($handle);
        static::getContainer()->get('doctrine.orm.entity_manager')->flush();
        return $user;
    }
}
