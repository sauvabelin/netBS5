<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\LldapClientInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** netbs:ldap:sync against netbs5_test (rolled back) and an in-memory lldap. */
final class LdapSyncCommandTest extends KernelTestCase
{
    use DirectoryFixturesTrait;

    private FakeLldap $lldap;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
        $this->lldap = new FakeLldap();
        static::getContainer()->set(LldapClientInterface::class, $this->lldap);
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    private function sync(bool $dryRun = true): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:ldap:sync'));
        $tester->execute($dryRun ? ['--dry-run' => true] : []);
        return $tester;
    }

    public function testBrokenRuleFailsWithoutSyncing(): void
    {
        $this->seedPublishedTree();
        $this->computedGroup('dirproj-cassé', '{nope');

        $tester = $this->sync();
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('dirproj-cassé', $display);
        self::assertSame([], $this->lldap->groups, 'nothing synced');
    }

    public function testSucceeds(): void
    {
        $this->seedPublishedTree();

        $tester = $this->sync();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame([], $this->lldap->users, 'dry run writes nothing');
        self::assertSame([], $this->lldap->groups, 'dry run writes nothing');
    }

    public function testRealRunAppliesTheChanges(): void
    {
        $this->seedPublishedTree();

        $tester = $this->sync(dryRun: false);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertArrayHasKey('dirproj.user', $this->lldap->users);
        self::assertSame(['dirproj.user'], $this->lldap->membersOf('dirproj-GA'));
    }

    public function testRealRunFailsWhenTheReportHasErrors(): void
    {
        $this->seedPublishedTree();
        $this->lldap->failOnCreateGroup = 'dirproj-GA';

        $tester = $this->sync(dryRun: false);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertArrayHasKey('dirproj.user', $this->lldap->users, 'the rest of the sync is applied');
    }

    public function testRealRunFailsWhenLldapIsDown(): void
    {
        $this->seedPublishedTree();
        $this->lldap->down = true;

        self::assertSame(Command::FAILURE, $this->sync(dryRun: false)->getStatusCode());
    }
}
