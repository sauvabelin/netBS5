<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Directory\DirectorySyncRunner;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Listener\DirectoryChangeListener;
use NetBS\AuthBundle\Message\SyncDirectory;
use NetBS\AuthBundle\MessageHandler\SyncDirectoryHandler;
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
        $this->seedGroupeTree();

        self::assertNotNull($this->runner()->run());

        self::assertSame(['dirproj.user'], $this->lldap->membersOf('dirproj-GA'));
    }

    public function testRequestCoveredByALaterRunIsSkipped(): void
    {
        $this->seedGroupeTree();
        $requestedAt = new \DateTimeImmutable('-1 minute');
        $this->runner()->run();

        self::assertNull($this->runner()->run($requestedAt));
        self::assertNotNull($this->runner()->run(new \DateTimeImmutable('+1 minute')));
        self::assertNotNull($this->runner()->run(), 'no request time: always runs');
    }

    public function testHandlerLogsAFailedRunWithoutThrowingAndItCoversNothing(): void
    {
        $this->seedGroupeTree();
        $this->lldap->down = true;

        static::getContainer()->get(SyncDirectoryHandler::class)(new SyncDirectory());

        $this->lldap->down = false;
        self::assertNotNull($this->runner()->run(new \DateTimeImmutable('-1 minute')));
    }

    public function testFlushOfDirectoryDataQueuesADelayedSync(): void
    {
        $ids = $this->seedGroupeTree();
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
        $ids = $this->seedGroupeTree();
        $user = $this->em->find(BSUser::class, $ids['user']);

        $user->setPassword('changed');
        $this->em->flush();

        self::assertSame([], $this->transport()->getSent());
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
