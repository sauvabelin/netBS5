<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * lldap management client. Talks to lldap's HTTP API (JWT login + GraphQL),
 * never to the LDAP port: netBS writes the directory, other services read it
 * over LDAP. Schema reference: https://github.com/lldap/lldap/blob/v0.6.3/schema.graphql
 */
final class LldapClient implements LldapClientInterface, ResetInterface
{
    private ?string $token = null;

    /**
     * @param HttpClientInterface $http scoped to LLDAP_URL (`lldap.client`)
     */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $adminUsername,
        private readonly string $adminPassword,
    ) {
    }

    public function listUsers(): array
    {
        $data = $this->graphql('{ users { id email displayName firstName lastName attributes { name value } } }');

        return array_map(static function (array $user): array {
            $attributes = array_fill_keys(array_keys(self::USER_ATTRIBUTES), null);
            foreach ($user['attributes'] as $attribute) {
                if (array_key_exists($attribute['name'], $attributes) && $attribute['value'] !== []) {
                    $attributes[$attribute['name']] = (string) $attribute['value'][0];
                }
            }

            return ['attributes' => $attributes] + $user;
        }, $data['users']);
    }

    public function listGroups(): array
    {
        $data = $this->graphql('{ groups { id displayName attributes { name value } users { id } } }');

        return array_map(static function (array $group): array {
            $netbsId = null;
            $netbsRole = null;
            $netbsComputedId = null;
            foreach ($group['attributes'] as $attribute) {
                if ($attribute['value'] === []) {
                    continue;
                }
                if ($attribute['name'] === self::NETBS_ID_ATTRIBUTE) {
                    $netbsId = (int) $attribute['value'][0];
                } elseif ($attribute['name'] === self::NETBS_ROLE_ATTRIBUTE) {
                    $netbsRole = (string) $attribute['value'][0];
                } elseif ($attribute['name'] === self::NETBS_COMPUTED_ATTRIBUTE) {
                    $netbsComputedId = (int) $attribute['value'][0];
                }
            }

            return [
                'id' => $group['id'],
                'displayName' => $group['displayName'],
                'netbsId' => $netbsId,
                'netbsRole' => $netbsRole,
                'netbsComputedId' => $netbsComputedId,
                'members' => array_column($group['users'], 'id'),
            ];
        }, $data['groups']);
    }

    public function groupAttributes(): array
    {
        return $this->schemaAttributes('group');
    }

    public function addGroupAttribute(string $name, string $type): void
    {
        $this->addAttribute('group', $name, $type);
    }

    public function userAttributes(): array
    {
        return $this->schemaAttributes('user');
    }

    public function addUserAttribute(string $name, string $type): void
    {
        $this->addAttribute('user', $name, $type);
    }

    /** @param 'user'|'group' $kind */
    private function schemaAttributes(string $kind): array
    {
        $data = $this->graphql(sprintf('{ schema { %sSchema { attributes { name } } } }', $kind));
        return array_column($data['schema'][$kind . 'Schema']['attributes'], 'name');
    }

    /** @param 'user'|'group' $kind */
    private function addAttribute(string $kind, string $name, string $type): void
    {
        $this->graphql(
            sprintf('mutation($name: String!, $type: AttributeType!) { add%sAttribute(name: $name, attributeType: $type, isList: false, isVisible: true, isEditable: false) { ok } }', ucfirst($kind)),
            ['name' => $name, 'type' => $type],
        );
    }

    public function createUser(DirectoryUser $user): void
    {
        $fields = ['id' => $user->uid] + $this->userFields($user);
        $set = $this->attributeValues($user);
        if ($set !== []) {
            $fields['attributes'] = $set;
        }
        $this->graphql('mutation($user: CreateUserInput!) { createUser(user: $user) { id } }', ['user' => $fields]);
    }

    public function updateUser(DirectoryUser $user): void
    {
        $fields = ['id' => $user->uid] + $this->userFields($user);
        $set = $this->attributeValues($user);
        if ($set !== []) {
            $fields['insertAttributes'] = $set;
        }
        $unset = array_keys(array_filter($user->attributes(), static fn (?string $v) => $v === null));
        if ($unset !== []) {
            $fields['removeAttributes'] = $unset;
        }
        $this->graphql('mutation($user: UpdateUserInput!) { updateUser(user: $user) { ok } }', ['user' => $fields]);
    }

    /** @return list<array{name: string, value: string[]}> the attributes $user has a value for */
    private function attributeValues(DirectoryUser $user): array
    {
        $values = [];
        foreach ($user->attributes() as $name => $value) {
            if ($value !== null) {
                $values[] = ['name' => $name, 'value' => [$value]];
            }
        }
        return $values;
    }

    public function deleteUser(string $uid): void
    {
        $this->graphql('mutation($id: String!) { deleteUser(userId: $id) { ok } }', ['id' => $uid]);
    }

    public function createGroup(string $displayName, ?int $netbsId, ?string $netbsRole = null, ?int $netbsComputedId = null): int
    {
        $request = ['displayName' => $displayName, 'attributes' => []];
        if ($netbsId !== null) {
            $request['attributes'][] = ['name' => self::NETBS_ID_ATTRIBUTE, 'value' => [(string) $netbsId]];
        }
        if ($netbsRole !== null) {
            $request['attributes'][] = ['name' => self::NETBS_ROLE_ATTRIBUTE, 'value' => [$netbsRole]];
        }
        if ($netbsComputedId !== null) {
            $request['attributes'][] = ['name' => self::NETBS_COMPUTED_ATTRIBUTE, 'value' => [(string) $netbsComputedId]];
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

    /**
     * Drops the cached JWT. Called between messenger messages (kernel.reset),
     * so a long-running worker logs in afresh for each sync.
     */
    public function reset(): void
    {
        $this->token = null;
    }

    private function graphql(string $query, array $variables = []): array
    {
        $payload = ['query' => $query, 'variables' => (object) $variables];
        try {
            $body = $this->request('/api/graphql', $payload, $this->token());
        } catch (LldapUnauthorizedException) {
            // Token expired mid-run: log in again, retry once.
            $this->reset();
            $body = $this->request('/api/graphql', $payload, $this->token());
        }

        if (!empty($body['errors'])) {
            $messages = array_map(static fn (array $e): string => $e['message'] ?? json_encode($e), $body['errors']);
            throw new LldapClientException('lldap GraphQL error: ' . implode('; ', $messages));
        }
        return $body['data'] ?? [];
    }

    private function token(): string
    {
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
            if ($bearer !== null && $response->getStatusCode() === 401) {
                throw new LldapUnauthorizedException(sprintf('lldap call POST %s rejected the token', $path));
            }
            return $response->toArray();
        } catch (HttpClientExceptionInterface $e) {
            throw new LldapClientException(sprintf('lldap call POST %s failed: %s', $path, $e->getMessage()), 0, $e);
        }
    }
}
