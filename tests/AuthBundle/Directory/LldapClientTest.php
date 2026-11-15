<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryUser;
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

    public function testNetbsUserAttributesAreReadWrittenAndCleared(): void
    {
        $sent = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent[] = json_decode($options['body'] ?? 'null', true);
            return match (count($sent)) {
                1 => $this->json(['token' => 't']),
                2 => $this->json(['data' => ['users' => [
                    ['id' => 'alice', 'email' => 'a@x.org', 'displayName' => 'A', 'firstName' => 'A', 'lastName' => 'T',
                        'attributes' => [['name' => 'netbs-email', 'value' => ['a@x.org']], ['name' => 'netbs-quota', 'value' => ['10485760']]]],
                    ['id' => 'bob', 'email' => 'bob@no-email.invalid', 'displayName' => 'B', 'firstName' => 'B', 'lastName' => 'T',
                        'attributes' => []],
                ]]]),
                default => $this->json(['data' => ['ok' => true]]),
            };
        }, 'http://lldap.test');
        $client = $this->client($http);

        $users = $client->listUsers();
        self::assertSame(['netbs-email' => 'a@x.org', 'netbs-quota' => '10485760', 'netbs-role' => null], $users[0]['attributes']);
        self::assertSame(['netbs-email' => null, 'netbs-quota' => null, 'netbs-role' => null], $users[1]['attributes']);

        $client->createUser(new DirectoryUser('carol', 'c@x.org', 'C', 'C', 'T', contactEmail: 'c@x.org', quota: 10485760));
        self::assertSame([['name' => 'netbs-email', 'value' => ['c@x.org']], ['name' => 'netbs-quota', 'value' => ['10485760']]], $sent[2]['variables']['user']['attributes']);

        $client->updateUser(new DirectoryUser('alice', 'a@x.org', 'A', 'A', 'T', contactEmail: 'a@x.org'));
        self::assertSame([['name' => 'netbs-email', 'value' => ['a@x.org']]], $sent[3]['variables']['user']['insertAttributes']);
        self::assertSame(['netbs-quota', 'netbs-role'], $sent[3]['variables']['user']['removeAttributes']);

        $client->updateUser(new DirectoryUser('alice', 'alice@no-email.invalid', 'A', 'A', 'T'));
        self::assertSame(['netbs-email', 'netbs-quota', 'netbs-role'], $sent[4]['variables']['user']['removeAttributes']);
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
