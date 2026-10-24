<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Listener;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use NetBS\AuthBundle\Listener\RevokeOidcGrantsListener;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RevokeOidcGrantsListenerTest extends TestCase
{
    private MockHttpClient $http;

    private function listener(array $responses): RevokeOidcGrantsListener
    {
        $this->http = new MockHttpClient($responses);
        $hydra = (new \ReflectionClass(HydraAdminClient::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(HydraAdminClient::class, 'http'))->setValue($hydra, $this->http);
        (new \ReflectionProperty(HydraAdminClient::class, 'logger'))->setValue($hydra, new \Psr\Log\NullLogger());

        return new RevokeOidcGrantsListener($hydra, new \Psr\Log\NullLogger());
    }

    private function update(RevokeOidcGrantsListener $listener, array $changeSet, string $username = 'alice'): void
    {
        $user = $this->createMock(BSUser::class);
        $user->method('getUsername')->willReturn($username);
        $listener->preUpdate(new PreUpdateEventArgs($user, $this->createMock(EntityManagerInterface::class), $changeSet));
        $listener->postFlush();
    }

    public function testPasswordChangeRevokesConsentAndLoginSessions(): void
    {
        $listener = $this->listener([new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 204])]);

        $this->update($listener, ['passwordChangedAt' => [null, new \DateTimeImmutable()]]);

        $this->assertSame(2, $this->http->getRequestsCount());
    }

    public function testDeactivationRevokesUnderTheLowercaseSubject(): void
    {
        $urls = [];
        $record = function (string $method, string $url) use (&$urls) {
            $urls[] = $url;
            return new MockResponse('', ['http_code' => 204]);
        };
        $listener = $this->listener([$record, $record]);

        $this->update($listener, ['isActive' => [true, false]], 'Equipe');

        $this->assertCount(2, $urls);
        foreach ($urls as $url) {
            $this->assertStringContainsString('subject=equipe', $url);
        }
    }

    public function testUnrelatedChangesDoNothing(): void
    {
        $listener = $this->listener([]);

        $this->update($listener, ['isActive' => [false, true], 'email' => ['a@x', 'b@x']]);

        $this->assertSame(0, $this->http->getRequestsCount());
    }

    public function testHydraFailureDoesNotThrow(): void
    {
        $listener = $this->listener([new MockResponse('down', ['http_code' => 503])]);

        $this->update($listener, ['passwordChangedAt' => [null, new \DateTimeImmutable()]]);

        $this->assertSame(1, $this->http->getRequestsCount());
    }

}
