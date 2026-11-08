<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use App\Entity\BSUser;
use App\Tests\Support\CollectingLogger;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Directory\DirectorySyncRunner;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Listener\DirectoryChangeListener;
use NetBS\AuthBundle\Message\SyncDirectory;
use NetBS\AuthBundle\MessageHandler\SyncDirectoryHandler;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\SecureBundle\Entity\Role;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * The background sync: runner, worker handler and the flush listener that
 * queues it, against netbs5_test (rolled back) and an in-memory lldap.
 */
final class DirectorySyncRunnerTest extends KernelTestCase
{
    use DirectoryFixturesTrait;

    private FakeLldap $lldap;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->db = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->lldap = new FakeLldap();
        static::getContainer()->set(LldapClientInterface::class, $this->lldap);
        // The last sync time is cached, which the rollback doesn't undo.
        static::getContainer()->get('cache.app')->clear();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    public function testRunApplies(): void
    {
        $this->seedPublishedTree();

        self::assertNotNull($this->runner()->run());

        self::assertSame(['dirproj.user'], $this->lldap->membersOf('dirproj-GA'));
    }

    public function testRequestCoveredByALaterRunIsSkipped(): void
    {
        $this->seedPublishedTree();
        $requestedAt = new \DateTimeImmutable('-1 minute');
        $this->runner()->run();

        self::assertNull($this->runner()->run($requestedAt));
        self::assertNotNull($this->runner()->run(new \DateTimeImmutable('+1 minute')));
        self::assertNotNull($this->runner()->run(), 'no request time: always runs');
    }

    public function testHandlerLogsAFailedRunWithoutThrowingAndItCoversNothing(): void
    {
        $this->seedPublishedTree();
        $this->lldap->down = true;

        static::getContainer()->get(SyncDirectoryHandler::class)(new SyncDirectory());

        $this->lldap->down = false;
        self::assertNotNull($this->runner()->run(new \DateTimeImmutable('-1 minute')));
    }

    public function testFlushOfDirectoryDataQueuesADelayedSync(): void
    {
        $ids = $this->seedPublishedTree();
        $groupe = $this->em->find(static::getContainer()->get(FichierConfig::class)->getGroupeClass(), $ids['ga']);

        $groupe->setNom('dirproj-GA renamed');
        $this->em->flush();

        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(SyncDirectory::class, $sent[0]->getMessage());
        self::assertSame(DirectoryChangeListener::DELAY_MS, $sent[0]->last(DelayStamp::class)->getDelay());
    }

    public function testPasswordChangeDoesNotQueueASync(): void
    {
        $ids = $this->seedPublishedTree();
        $user = $this->em->find(BSUser::class, $ids['user']);

        $user->setPassword('changed');
        $this->em->flush();

        self::assertSame([], $this->transport()->getSent());
    }

    public function testHandlerLogsAWarningWhenTheReportHasErrors(): void
    {
        $this->seedPublishedTree();
        $this->lldap->failOnCreateGroup = 'dirproj-GA';
        $logger = new CollectingLogger();

        (new SyncDirectoryHandler($this->runner(), $logger))(new SyncDirectory());

        self::assertSame(1, $logger->count('warning'));
        self::assertSame(0, $logger->count('error'));
    }

    public function testHandlerLogsNothingOnACleanRun(): void
    {
        $this->seedPublishedTree();
        $logger = new CollectingLogger();

        (new SyncDirectoryHandler($this->runner(), $logger))(new SyncDirectory());

        self::assertSame([], $logger->records);
    }

    public function testHandlerLogsAnUnconfiguredDirectoryWithoutThrowing(): void
    {
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_publication_rules');
        $this->db->executeStatement('DELETE FROM netbs_auth_directory_computed_groups');
        $logger = new CollectingLogger();

        (new SyncDirectoryHandler($this->runner(), $logger))(new SyncDirectory());

        self::assertSame(1, $logger->count('error'));
        self::assertSame([], $this->lldap->users, 'nothing synced');
    }

    /** @dataProvider ruleContexts */
    public function testStoredRuleEditQueuesASyncOnlyInADirectoryContext(string $context, bool $queued): void
    {
        $rule = $this->em->find(StoredRule::class, $this->storedRule($context, self::blockRule('groupe.types', ['types' => [1]])));

        $rule->setDefinition(self::blockRule('groupe.types', ['types' => [2]]));
        $this->em->flush();

        self::assertCount($queued ? 1 : 0, $this->transport()->getSent());
    }

    public function ruleContexts(): array
    {
        return [
            'groupe' => ['groupe', true],
            'attribution_sous_groupe' => ['attribution_sous_groupe', true],
            'membre' => ['membre', true],
            'other' => ['dirproj-other', false],
        ];
    }

    public function testRoleGrantQueuesASync(): void
    {
        $ids = $this->seedGroupeTree();
        $user = $this->em->find(BSUser::class, $ids['user']);
        $role = $this->em->find(Role::class, $this->role('ROLE_WIKI_ADMIN'));

        $user->addRole($role);
        $this->em->flush();

        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);
        self::assertInstanceOf(SyncDirectory::class, $sent[0]->getMessage());
    }

    private function runner(): DirectorySyncRunner
    {
        return static::getContainer()->get(DirectorySyncRunner::class);
    }

    private function transport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }
}
