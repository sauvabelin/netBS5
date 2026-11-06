<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\LldapClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LldapClientTest extends TestCase
{
    public function testExpiredTokenIsRenewedOnce(): void
    {
        $http = new MockHttpClient(baseUri: 'http://lldap.test', responseFactory: [
            $this->json(['token' => 'old']),
            new MockResponse('', ['http_code' => 401]),
            $this->json(['token' => 'new']),
            $this->json(['data' => ['schema' => ['groupSchema' => ['attributes' => [['name' => 'netbs-id']]]]]]),
        ]);

        self::assertSame(['netbs-id'], $this->client($http)->groupAttributes());
        self::assertSame(4, $http->getRequestsCount());
    }

    public function testComputedGroupAttributeIsReadAndWritten(): void
    {
        $sent = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent[] = json_decode($options['body'] ?? 'null', true);
            return match (count($sent)) {
                1 => $this->json(['token' => 't']),
                2 => $this->json(['data' => ['groups' => [[
                    'id' => 7, 'displayName' => 'chefs', 'users' => [['id' => 'alice']],
                    'attributes' => [['name' => 'netbs-computed-id', 'value' => ['3']]],
                ]]]]),
                default => $this->json(['data' => ['createGroupWithDetails' => ['id' => 8]]]),
            };
        }, 'http://lldap.test');
        $client = $this->client($http);

        self::assertSame([['id' => 7, 'displayName' => 'chefs', 'netbsId' => null, 'netbsRole' => null, 'netbsComputedId' => 3, 'members' => ['alice']]], $client->listGroups());
        self::assertSame(8, $client->createGroup('chefs', null, null, 3));
        self::assertSame([['name' => 'netbs-computed-id', 'value' => ['3']]], $sent[2]['variables']['request']['attributes']);
    }

    private function client(MockHttpClient $http): LldapClient
    {
        return new LldapClient($http, 'admin', 'secret');
    }

    private function json(array $data): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
