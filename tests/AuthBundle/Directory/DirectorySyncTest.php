<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectorySnapshot;
use NetBS\AuthBundle\Directory\DirectorySync;
use NetBS\AuthBundle\Directory\DirectoryUser;
use PHPUnit\Framework\TestCase;

final class DirectorySyncTest extends TestCase
{
    private FakeLldap $lldap;

    protected function setUp(): void
    {
        $this->lldap = new FakeLldap();
    }

    public function testEmptyDirectoryIsFilledFromNetbs(): void
    {
        $report = $this->sync(
            [$this->user('alice'), $this->user('bob')],
            [new DirectoryGroup(10, 'Troupe X', ['alice'])],
        );

        self::assertSame([], $report->errors);
        self::assertSame(['netbs-id', 'netbs-role'], $this->lldap->attributes);
        self::assertSame(['alice', 'bob'], $this->lldap->membersOf(DirectorySync::MANAGED_USERS_GROUP));
        self::assertSame(['alice'], $this->lldap->membersOf('Troupe X'));
    }

    public function testSecondRunIsANoOp(): void
    {
        $users = [$this->user('alice')];
        $groups = [new DirectoryGroup(10, 'Troupe X', ['alice'])];
        $this->sync($users, $groups);

        $report = $this->sync($users, $groups);

        self::assertSame([], $report->changes);
    }

    public function testUsersGroupsAndMembershipFollowNetbs(): void
    {
        $this->sync([$this->user('alice'), $this->user('bob')], [
            new DirectoryGroup(10, 'Troupe X', ['alice']),
            new DirectoryGroup(11, 'Meute Y', ['bob']),
        ]);

        $this->sync([$this->user('bob')], [
            new DirectoryGroup(10, 'Troupe X renamed', ['bob']),
        ]);

        self::assertArrayNotHasKey('alice', $this->lldap->users);
        self::assertSame(['bob'], $this->lldap->membersOf('Troupe X renamed'));
        self::assertNull($this->lldap->groupId('Meute Y'));
        self::assertNull($this->lldap->groupId('Troupe X'));
    }

    public function testUnmanagedEntriesAreLeftAlone(): void
    {
        $this->lldap->users['admin'] = ['id' => 'admin', 'email' => '', 'displayName' => 'Administrator', 'firstName' => '', 'lastName' => ''];
        $adminsGroup = $this->lldap->createGroup('lldap_admin', null);
        $this->lldap->addUserToGroup('admin', $adminsGroup);

        $report = $this->sync([$this->user('admin')], [new DirectoryGroup(10, 'Troupe X', ['admin'])]);

        self::assertArrayHasKey('admin', $this->lldap->users);
        self::assertSame(['admin'], $this->lldap->membersOf('lldap_admin'));
        self::assertSame([], $this->lldap->membersOf('Troupe X'));
        self::assertCount(1, $report->errors);
    }

    public function testDryRunChangesNothing(): void
    {
        $report = $this->sync([$this->user('alice')], [new DirectoryGroup(10, 'Troupe X', ['alice'])], dryRun: true);

        self::assertContains('create user alice', $report->changes);
        self::assertContains('add alice to "Troupe X"', $report->changes);
        self::assertSame([], $this->lldap->users);
        self::assertSame([], $this->lldap->attributes);
    }

    public function testFailedOperationIsReportedAndSyncContinues(): void
    {
        $this->lldap->failOnCreateUser = 'alice';

        $report = $this->sync([$this->user('alice'), $this->user('bob')], [new DirectoryGroup(10, 'Troupe X', ['alice', 'bob'])]);

        self::assertCount(1, $report->errors);
        self::assertSame(['bob'], $this->lldap->membersOf('Troupe X'));
    }

    public function testRoleGroupsAreTaggedAndMatchedByRole(): void
    {
        $this->lldap->attributes = ['netbs-id'];
        $this->sync([$this->user('alice')], [
            new DirectoryGroup(10, 'Troupe X', []),
            new DirectoryGroup(null, 'wiki-admins', ['alice'], 'ROLE_WIKI_ADMIN'),
        ]);
        $id = $this->lldap->groupId('wiki-admins');
        self::assertSame(['netbs-id', 'netbs-role'], $this->lldap->attributes);
        self::assertSame('ROLE_WIKI_ADMIN', $this->lldap->groups[$id]['netbsRole']);
        self::assertSame(['alice'], $this->lldap->membersOf('wiki-admins'));

        // Renamed in code, emptied: same lldap group, kept even with no members.
        $this->sync([$this->user('alice')], [
            new DirectoryGroup(10, 'Troupe X', []),
            new DirectoryGroup(null, 'wiki-admin', [], 'ROLE_WIKI_ADMIN'),
        ]);
        self::assertSame($id, $this->lldap->groupId('wiki-admin'));
        self::assertSame([], $this->lldap->membersOf('wiki-admin'));
        self::assertNotNull($this->lldap->groupId('Troupe X'));
    }

    /**
     * @param DirectoryUser[] $users
     * @param DirectoryGroup[] $groups groupe groups and role groups
     */
    private function sync(array $users, array $groups, bool $dryRun = false)
    {
        $usersByUid = [];
        foreach ($users as $user) {
            $usersByUid[$user->uid] = $user;
        }
        $groupsById = [];
        $roleGroups = [];
        foreach ($groups as $group) {
            if ($group->role !== null) {
                $roleGroups[$group->role] = $group;
            } else {
                $groupsById[$group->netbsId] = $group;
            }
        }
        return (new DirectorySync($this->lldap))->sync(new DirectorySnapshot($usersByUid, $groupsById, $roleGroups), $dryRun);
    }

    private function user(string $uid): DirectoryUser
    {
        return new DirectoryUser($uid, "$uid@example.org", ucfirst($uid), ucfirst($uid), 'Test');
    }

    public function testDeletedUserIsNotRemovedFromItsGroupsAgain(): void
    {
        $this->sync([$this->user('alice'), $this->user('bob')], [new DirectoryGroup(10, 'Troupe X', ['alice', 'bob'])]);

        $report = $this->sync([$this->user('bob')], [new DirectoryGroup(10, 'Troupe X', ['bob'])]);

        self::assertSame([], $report->errors);
        self::assertSame(['delete user alice'], $report->changes);
        self::assertSame(['bob'], $this->lldap->membersOf('Troupe X'));
    }
}
