<?php

declare(strict_types=1);

namespace App\Tests\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\UserModule\AppIdentityClientPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Covers the per-client `metadata.required_flag` gate: a client that requires
 * e.g. nextcloud_account must refuse users without that flag.
 */
final class AppIdentityClientPolicyTest extends TestCase
{
    public function testClientWithoutRequiredFlagAllowsActiveUser(): void
    {
        $policy = $this->policy($this->user(nextcloud: false, wiki: false), ['metadata' => []]);

        $this->assertTrue($policy->canAccess($this->identity(), 'acme'));
    }

    public function testRequiredFlagSetAllows(): void
    {
        $policy = $this->policy($this->user(nextcloud: true, wiki: false), ['metadata' => ['required_flag' => 'nextcloud_account']]);

        $this->assertTrue($policy->canAccess($this->identity(), 'acme'));
    }

    public function testRequiredFlagMissingDenies(): void
    {
        $policy = $this->policy($this->user(nextcloud: true, wiki: false), ['metadata' => ['required_flag' => 'wiki_account']]);

        $this->assertFalse($policy->canAccess($this->identity(), 'acme'));
    }

    public function testUnknownRequiredFlagDenies(): void
    {
        $policy = $this->policy($this->user(nextcloud: true, wiki: true), ['metadata' => ['required_flag' => 'is_god']]);

        $this->assertFalse($policy->canAccess($this->identity(), 'acme'));
    }

    public function testUnknownClientDenies(): void
    {
        $policy = $this->policy($this->user(nextcloud: true, wiki: true), null);

        $this->assertFalse($policy->canAccess($this->identity(), 'acme'));
    }

    public function testDisabledIdentityDenies(): void
    {
        $policy = $this->policy($this->user(nextcloud: true, wiki: true), ['metadata' => []]);

        $this->assertFalse($policy->canAccess($this->identity(disabled: true), 'acme'));
    }

    private function user(bool $nextcloud, bool $wiki): BSUser
    {
        $user = $this->createMock(BSUser::class);
        $user->method('hasNextcloudAccount')->willReturn($nextcloud);
        $user->method('hasWikiAccount')->willReturn($wiki);
        return $user;
    }

    private function identity(bool $disabled = false): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'alice',
            preferredUsername: 'alice',
            email: null,
            displayName: 'Alice',
            groups: [],
            isDisabled: $disabled,
        );
    }

    /**
     * @param array<string, mixed>|null $hydraClient null = Hydra answers 404
     */
    private function policy(BSUser $user, ?array $hydraClient): AppIdentityClientPolicy
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($user);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $response = $hydraClient === null
            ? new MockResponse('', ['http_code' => 404])
            : new MockResponse(json_encode($hydraClient, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        $hydra = (new \ReflectionClass(HydraAdminClient::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(HydraAdminClient::class, 'http'))->setValue($hydra, new MockHttpClient([$response]));
        (new \ReflectionProperty(HydraAdminClient::class, 'logger'))->setValue($hydra, new \Psr\Log\NullLogger());

        return new AppIdentityClientPolicy($em, $hydra);
    }
}
