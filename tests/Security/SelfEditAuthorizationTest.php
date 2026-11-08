<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A user may edit their own account (UserVoter), but through the generic
 * XEditable endpoint the client picks the field: only the email is theirs to change.
 */
final class SelfEditAuthorizationTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private BSUser $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user   = $this->persistUser($this->client, 'self-edit-user', email: 'self-edit@example.test', roles: ['ROLE_USER']);
        $this->client->loginUser($this->user, 'netbs');
    }

    private function editSelf(array $name, mixed $value): void
    {
        $this->client->request('POST', '/netBS/utils/xeditable', [
            'pk'    => (string) $this->user->getId(),
            'name'  => json_encode($name + ['multiple' => false, 'itemClass' => base64_encode(BSUser::class)]),
            'value' => $value,
        ]);
    }

    public function test_user_cannot_grant_themselves_a_role(): void
    {
        $admin = $this->client->getContainer()->get('doctrine.orm.entity_manager')
            ->getRepository(Role::class)->findOneBy(['role' => 'ROLE_ADMIN']);

        $this->editSelf(['field' => 'roles', 'type' => 'select2_document', 'class' => base64_encode(Role::class), 'multiple' => 'true'], [(string) $admin->getId()]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotContains('ROLE_ADMIN', $this->reloadUser($this->client, 'self-edit-user')->getRolesAsString());
    }

    public function test_user_cannot_toggle_their_own_flags(): void
    {
        $this->editSelf(['field' => 'nextcloudAdmin', 'type' => 'switch'], ['1']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->reloadUser($this->client, 'self-edit-user')->isNextcloudAdmin());
    }

    public function test_user_can_change_their_own_email(): void
    {
        $this->editSelf(['field' => 'email', 'type' => 'email'], 'self-edit-new@example.test');

        $this->assertResponseIsSuccessful();
        $this->assertSame('self-edit-new@example.test', $this->reloadUser($this->client, 'self-edit-user')->getEmail());
    }
}
