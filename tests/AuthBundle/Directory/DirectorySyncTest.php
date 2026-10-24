<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryGroup;
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
        self::assertTrue($this->lldap->hasAttribute);
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

    public function testMembershipRenameAndRemovalFollowNetbs(): void
    {
        $this->sync([$this->user('alice'), $this->user('bob')], [
            new DirectoryGroup(10, 'Troupe X', ['alice']),
            new DirectoryGroup(11, 'Meute Y', ['bob']),
        ]);

        $this->sync([$this->user('alice'), $this->user('bob')], [
            new DirectoryGroup(10, 'Troupe X renamed', ['bob']),
        ]);

        self::assertSame(['bob'], $this->lldap->membersOf('Troupe X renamed'));
        self::assertNull($this->lldap->groupId('Meute Y'));
        self::assertNull($this->lldap->groupId('Troupe X'));
    }

    public function testRemovedUserIsDeleted(): void
    {
        $this->sync([$this->user('alice'), $this->user('bob')], []);

        $report = $this->sync([$this->user('alice')], []);

        self::assertContains('delete user bob', $report->changes);
        self::assertArrayNotHasKey('bob', $this->lldap->users);
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
        self::assertFalse($this->lldap->hasAttribute);
    }

    public function testFailedOperationIsReportedAndSyncContinues(): void
    {
        $this->lldap->failOnCreateUser = 'alice';

        $report = $this->sync([$this->user('alice'), $this->user('bob')], [new DirectoryGroup(10, 'Troupe X', ['alice', 'bob'])]);

        self::assertCount(1, $report->errors);
        self::assertSame(['bob'], $this->lldap->membersOf('Troupe X'));
    }

    /**
     * @param DirectoryUser[] $users
     * @param DirectoryGroup[] $groups
     */
    private function sync(array $users, array $groups, bool $dryRun = false)
    {
        $usersByUid = [];
        foreach ($users as $user) {
            $usersByUid[$user->uid] = $user;
        }
        $groupsById = [];
        foreach ($groups as $group) {
            $groupsById[$group->netbsId] = $group;
        }
        return (new DirectorySync($this->lldap))->sync($usersByUid, $groupsById, $dryRun);
    }

    private function user(string $uid): DirectoryUser
    {
        return new DirectoryUser($uid, "$uid@example.org", ucfirst($uid), ucfirst($uid), 'Test');
    }
}
