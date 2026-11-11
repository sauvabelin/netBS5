<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\BSUser;
use App\Entity\LatestCreatedAccount;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Older accounts changed their password through paths that didn't clean up (reset by email,
 * admin change): their plaintext initial password is still stored although it no longer
 * works. The command deletes every stored initial password that isn't the current one.
 */
final class PurgeInitialPasswordsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $conn;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em   = static::getContainer()->get(EntityManagerInterface::class);
        $this->conn = $this->em->getConnection();
        $this->conn->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->conn->rollBack();
        parent::tearDown();
    }

    private function account(string $username, string $currentPassword, string $storedPassword): int
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new BSUser();
        $user->setUsername($username);
        $user->setPassword($hasher->hashPassword($user, $currentPassword));
        $user->setNewPasswordRequired(true);
        $latest = new LatestCreatedAccount();
        $latest->setUser($user);
        $latest->setPassword($storedPassword);
        $this->em->persist($user);
        $this->em->persist($latest);
        $this->em->flush();

        return $latest->getId();
    }

    private function runCommand(array $options = []): int
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:accounts:purge-initial-passwords'));

        return $tester->execute($options);
    }

    private function exists(int $latestId): bool
    {
        return (bool) $this->conn->fetchOne('SELECT COUNT(*) FROM sauvabelin_latest_created_accounts WHERE id = ?', [$latestId]);
    }

    public function test_deletes_only_initial_passwords_that_no_longer_work(): void
    {
        $stillValid = $this->account('purge-valid', 'initial-1', 'initial-1');
        $changed    = $this->account('purge-changed', 'chosen-by-user', 'initial-2');

        $this->assertSame(Command::SUCCESS, $this->runCommand());

        $this->assertTrue($this->exists($stillValid));
        $this->assertFalse($this->exists($changed));
        $this->em->clear();
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => 'purge-changed']);
        $this->assertTrue($user->isNewPasswordRequired(), 'only the plaintext goes; the flag is left as is');
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $changed = $this->account('purge-dry', 'chosen-by-user', 'initial-3');

        $this->assertSame(Command::SUCCESS, $this->runCommand(['--dry-run' => true]));

        $this->assertTrue($this->exists($changed));
    }
}
