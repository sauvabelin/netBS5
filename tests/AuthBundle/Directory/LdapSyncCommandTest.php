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

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
        static::getContainer()->set(LldapClientInterface::class, new FakeLldap());
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    private function sync(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:ldap:sync'));
        $tester->execute(['--dry-run' => true]);
        return $tester;
    }

    public function testReportsFrozenGroupsAndFails(): void
    {
        $this->seedPublishedTree();
        $this->computedGroup('dirproj-cassé', '{nope');

        $tester = $this->sync();
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 groups, 0 computed groups, 2 role groups, 1 frozen', $display);
        self::assertStringContainsString('gelé : dirproj-cassé — Groupe LDAP calculé « dirproj-cassé »', $display);
    }

    public function testSucceedsWithoutFrozenGroups(): void
    {
        $this->seedPublishedTree();

        $tester = $this->sync();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('0 frozen', $tester->getDisplay());
    }
}
