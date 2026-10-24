<?php

declare(strict_types=1);

namespace App\Ldap;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * lldap management client. Talks to lldap's HTTP API (JWT login + GraphQL),
 * never to the LDAP port: netBS writes the directory, other services read it
 * over LDAP. Schema reference: https://github.com/lldap/lldap/blob/v0.6.3/schema.graphql
 */
final class LldapClient implements LldapClientInterface
{
    private HttpClientInterface $http;
    private ?string $token = null;

    public function __construct(
        string $lldapUrl,
        private readonly string $adminUsername,
        private readonly string $adminPassword,
    ) {
        $this->http = HttpClient::createForBaseUri($lldapUrl, [
            'timeout' => 5,
            'max_duration' => 15,
        ]);
    }

    public function listUsers(): array
    {
        $data = $this->graphql('{ users { id email displayName firstName lastName } }');
        return $data['users'];
    }

    public function listGroups(): array
    {
        $data = $this->graphql('{ groups { id displayName attributes { name value } users { id } } }');

        return array_map(static function (array $group): array {
            $netbsId = null;
            foreach ($group['attributes'] as $attribute) {
                if ($attribute['name'] === DirectorySync::NETBS_ID_ATTRIBUTE && $attribute['value'] !== []) {
                    $netbsId = (int) $attribute['value'][0];
                }
            }

            return [
                'id' => $group['id'],
                'displayName' => $group['displayName'],
                'netbsId' => $netbsId,
                'members' => array_column($group['users'], 'id'),
            ];
        }, $data['groups']);
    }

    public function hasGroupAttribute(string $name): bool
    {
        $data = $this->graphql('{ schema { groupSchema { attributes { name } } } }');
        return in_array($name, array_column($data['schema']['groupSchema']['attributes'], 'name'), true);
    }

    public function addIntegerGroupAttribute(string $name): void
    {
        $this->graphql(
            'mutation($name: String!) { addGroupAttribute(name: $name, attributeType: INTEGER, isList: false, isVisible: true, isEditable: false) { ok } }',
            ['name' => $name],
        );
    }

    public function createUser(DirectoryUser $user): void
    {
        $this->graphql(
            'mutation($user: CreateUserInput!) { createUser(user: $user) { id } }',
            ['user' => ['id' => $user->uid] + $this->userFields($user)],
        );
    }

    public function updateUser(DirectoryUser $user): void
    {
        $this->graphql(
            'mutation($user: UpdateUserInput!) { updateUser(user: $user) { ok } }',
            ['user' => ['id' => $user->uid] + $this->userFields($user)],
        );
    }

    public function deleteUser(string $uid): void
    {
        $this->graphql('mutation($id: String!) { deleteUser(userId: $id) { ok } }', ['id' => $uid]);
    }

    public function createGroup(string $displayName, ?int $netbsId): int
    {
        $request = ['displayName' => $displayName];
        if ($netbsId !== null) {
            $request['attributes'] = [['name' => DirectorySync::NETBS_ID_ATTRIBUTE, 'value' => [(string) $netbsId]]];
        }

        $data = $this->graphql(
            'mutation($request: CreateGroupInput!) { createGroupWithDetails(request: $request) { id } }',
            ['request' => $request],
        );
        return $data['createGroupWithDetails']['id'];
    }

    public function renameGroup(int $groupId, string $displayName): void
    {
        $this->graphql(
            'mutation($group: UpdateGroupInput!) { updateGroup(group: $group) { ok } }',
            ['group' => ['id' => $groupId, 'displayName' => $displayName]],
        );
    }

    public function deleteGroup(int $groupId): void
    {
        $this->graphql('mutation($id: Int!) { deleteGroup(groupId: $id) { ok } }', ['id' => $groupId]);
    }

    public function addUserToGroup(string $uid, int $groupId): void
    {
        $this->graphql(
            'mutation($uid: String!, $gid: Int!) { addUserToGroup(userId: $uid, groupId: $gid) { ok } }',
            ['uid' => $uid, 'gid' => $groupId],
        );
    }

    public function removeUserFromGroup(string $uid, int $groupId): void
    {
        $this->graphql(
            'mutation($uid: String!, $gid: Int!) { removeUserFromGroup(userId: $uid, groupId: $gid) { ok } }',
            ['uid' => $uid, 'gid' => $groupId],
        );
    }

    private function userFields(DirectoryUser $user): array
    {
        return [
            'email' => $user->email,
            'displayName' => $user->displayName,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
        ];
    }

    private function graphql(string $query, array $variables = []): array
    {
        $body = $this->request('/api/graphql', ['query' => $query, 'variables' => (object) $variables], $this->token());

        if (!empty($body['errors'])) {
            $messages = array_map(static fn (array $e): string => $e['message'] ?? json_encode($e), $body['errors']);
            throw new LldapClientException('lldap GraphQL error: ' . implode('; ', $messages));
        }
        return $body['data'] ?? [];
    }

    private function token(): string
    {
        // lldap JWTs last a day; one sync run never outlives one.
        return $this->token ??= $this->request('/auth/simple/login', [
            'username' => $this->adminUsername,
            'password' => $this->adminPassword,
        ])['token'];
    }

    private function request(string $path, array $json, ?string $bearer = null): array
    {
        try {
            $response = $this->http->request('POST', $path, [
                'json' => $json,
                'auth_bearer' => $bearer,
            ]);
            return $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new LldapClientException(sprintf('lldap call POST %s failed: %s', $path, $e->getMessage()), 0, $e);
        }
    }
}
