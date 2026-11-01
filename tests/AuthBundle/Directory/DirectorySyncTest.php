<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectorySnapshot;
use NetBS\AuthBundle\Directory\DirectorySync;
use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\FrozenGroup;
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
        self::assertSame(['netbs-id', 'netbs-role', 'netbs-computed-id'], $this->lldap->attributes);
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
        self::assertSame(['netbs-id', 'netbs-role', 'netbs-computed-id'], $this->lldap->attributes);
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

    public function testCreatesComputedGroupWithAttribute(): void
    {
        $this->sync([$this->user('alice')], [new DirectoryGroup(null, 'chefs', ['alice'], computedId: 3)]);

        $id = $this->lldap->groupId('chefs');
        self::assertSame(3, $this->lldap->groups[$id]['netbsComputedId']);
        self::assertNull($this->lldap->groups[$id]['netbsId']);
        self::assertSame(['alice'], $this->lldap->membersOf('chefs'));
    }

    public function testRenamedComputedGroupIsRenamedNotDuplicated(): void
    {
        $this->sync([$this->user('alice')], [new DirectoryGroup(null, 'chefs', ['alice'], computedId: 3)]);
        $id = $this->lldap->groupId('chefs');

        $this->sync([$this->user('alice')], [new DirectoryGroup(null, 'responsables', ['alice'], computedId: 3)]);

        self::assertSame($id, $this->lldap->groupId('responsables'));
        self::assertNull($this->lldap->groupId('chefs'));
    }

    public function testComputedAndGroupeGroupsWithTheSameIdAreDistinct(): void
    {
        $this->sync([$this->user('alice')], [
            new DirectoryGroup(3, 'Troupe X', ['alice']),
            new DirectoryGroup(null, 'chefs', [], computedId: 3),
        ]);

        self::assertNotNull($this->lldap->groupId('Troupe X'));
        self::assertNotNull($this->lldap->groupId('chefs'));
    }

    public function testFrozenExistingGroupIsLeftUntouched(): void
    {
        $this->sync([$this->user('alice'), $this->user('bob')], [new DirectoryGroup(5, 'Troupe X', ['alice'])]);

        $report = $this->sync([$this->user('alice'), $this->user('bob')], [], frozen: [new FrozenGroup('id:5', 'Troupe X renamed', 'cassée')]);

        self::assertSame(['alice'], $this->lldap->membersOf('Troupe X'));
        self::assertSame([], $report->changes);
    }

    public function testFrozenMissingGroupIsNotCreated(): void
    {
        $report = $this->sync([$this->user('alice')], [], frozen: [new FrozenGroup('computed:2', 'chefs', 'cassée')]);

        self::assertNull($this->lldap->groupId('chefs'));
        self::assertSame([], $report->errors);
    }

    public function testUnfrozenUndesiredGroupIsStillDeleted(): void
    {
        $this->sync([$this->user('alice')], [new DirectoryGroup(5, 'Troupe X', []), new DirectoryGroup(6, 'Meute Y', [])]);

        $this->sync([$this->user('alice')], [], frozen: [new FrozenGroup('id:5', 'Troupe X', 'cassée')]);

        self::assertNotNull($this->lldap->groupId('Troupe X'));
        self::assertNull($this->lldap->groupId('Meute Y'));
    }

    public function testAddsComputedAttributeWhenMissing(): void
    {
        $this->lldap->attributes = ['netbs-id', 'netbs-role'];

        $this->sync([], []);

        self::assertSame(['netbs-id', 'netbs-role', 'netbs-computed-id'], $this->lldap->attributes);
    }

    /**
     * @param DirectoryUser[] $users
     * @param DirectoryGroup[] $groups groupe, computed and role groups
     * @param FrozenGroup[] $frozen
     */
    private function sync(array $users, array $groups, bool $dryRun = false, array $frozen = [])
    {
        $usersByUid = [];
        foreach ($users as $user) {
            $usersByUid[$user->uid] = $user;
        }
        $groupsById = [];
        $roleGroups = [];
        $computedGroups = [];
        foreach ($groups as $group) {
            if ($group->role !== null) {
                $roleGroups[$group->role] = $group;
            } elseif ($group->computedId !== null) {
                $computedGroups[$group->computedId] = $group;
            } else {
                $groupsById[$group->netbsId] = $group;
            }
        }
        $frozenByKey = [];
        foreach ($frozen as $group) {
            $frozenByKey[$group->key] = $group;
        }
        return (new DirectorySync($this->lldap))->sync(new DirectorySnapshot($usersByUid, $groupsById, $roleGroups, $computedGroups, $frozenByKey), $dryRun);
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
