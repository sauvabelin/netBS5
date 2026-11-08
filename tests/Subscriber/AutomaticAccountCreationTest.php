<?php

declare(strict_types=1);

namespace App\Tests\Subscriber;

use App\Entity\BSMembre;
use App\Entity\BSUser;
use App\Entity\LatestCreatedAccount;
use App\Settings\BsReferences;
use App\Subscriber\DoctrineUserAccountSubscriber;
use App\Tests\Support\RealRuleEngineTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Famille;
use NetBS\FichierBundle\Entity\Fonction;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The account-creation side of DoctrineUserAccountSubscriber: what an attribution save
 * writes once the « création automatique de compte » rule said yes (the decision itself is
 * in AccountCreationRuleTest).
 */
final class AutomaticAccountCreationTest extends KernelTestCase
{
    use RealRuleEngineTrait;

    private const FAMILLE  = 'AcctCreationTest';
    private const USERNAME = 'tierthree.acctcreationtest';

    private EntityManagerInterface $em;
    private RequestStack $requestStack;
    private BSMembre $membre;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $famille = new Famille();
        $famille->setNom(self::FAMILLE);
        $this->membre = new BSMembre();
        $this->membre->setPrenom('Tièrthree');
        $this->membre->setSexe('m');
        $this->membre->setStatut(BSMembre::INSCRIT);
        $this->membre->setNaissance(new \DateTime('2001-02-03'));
        $this->membre->setInscription(new \DateTime('2020-01-01'));
        $famille->addMembre($this->membre);
        $this->em->persist($famille);
        $this->em->flush();

        $this->requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $f  = self::FAMILLE;
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $db->executeStatement("DELETE a FROM sauvabelin_latest_created_accounts a JOIN sauvabelin_netbs_users u ON u.id = a.user_id WHERE u.username LIKE 'tierthree%' OR u.login_username LIKE 'tierthree%'");
        $db->executeStatement("DELETE FROM sauvabelin_netbs_users WHERE username LIKE 'tierthree%' OR login_username LIKE 'tierthree%'");
        $db->executeStatement("DELETE mb FROM sauvabelin_netbs_membres mb JOIN netbs_fichier_familles f ON f.id = mb.famille_id WHERE f.nom = '$f'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = '$f'");
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $this->em->clear();
    }

    /**
     * A subscriber that grants an account to every inscrit membre: no rule, legacy weight 0.
     * (The rule path would add the unsaved attribution to the membre, and the flush in
     * createUser would then try to persist it.)
     */
    private function subscriber(): DoctrineUserAccountSubscriber
    {
        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willReturnMap([
            ['bs', 'comptes.creation', true, null],
            ['bs', 'groupe.adabs_id', true, '0'],
            ['bs', 'fonction.weight.user_account', true, '0'],
        ]);

        return new DoctrineUserAccountSubscriber(
            static::getContainer()->get(UserPasswordHasherInterface::class),
            $this->createMock(MailerInterface::class),
            $this->requestStack,
            $params,
            new NullLogger(),
            $this->fichierRuleEvaluator(),
            new BsReferences($params),
        );
    }

    private function saveAttribution(): void
    {
        $fonction = new Fonction();
        $fonction->setNom('acct-test')->setPoids(100)->setAbbreviation('AT');
        $attribution = new Attribution();
        $attribution->setFonction($fonction);
        $attribution->setDateDebut(new \DateTime('-1 day'));
        $attribution->setMembre($this->membre);

        $this->subscriber()->postPersist(new LifecycleEventArgs($attribution, $this->em));
    }

    private function otherUser(string $username, ?string $loginUsername = null, bool $deleted = false, bool $linked = false): void
    {
        $user = new BSUser();
        $user->setUsername($username);
        $user->setPassword('placeholder-hash');
        if ($loginUsername !== null) {
            $user->setLoginUsername($loginUsername);
        }
        if ($linked) {
            $user->setMembre($this->membre);
        }
        $this->em->persist($user);
        $this->em->flush();

        if ($deleted) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    /** @return BSUser[] every user (soft-deleted included) linked to the test membre */
    private function usersOfMembre(): array
    {
        $this->em->clear();
        $this->em->getFilters()->disable('softdeleteable');
        try {
            return $this->em->getRepository(BSUser::class)->findBy(['membre' => $this->membre->getId()]);
        } finally {
            $this->em->getFilters()->enable('softdeleteable');
        }
    }

    private function latestAccountsFor(BSUser $user): array
    {
        return $this->em->getRepository(LatestCreatedAccount::class)->findBy(['user' => $user]);
    }

    private function latestAccountCount(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM sauvabelin_latest_created_accounts');
    }

    public function test_creates_an_account_named_after_the_membre(): void
    {
        $this->saveAttribution();

        $users = $this->usersOfMembre();
        $this->assertCount(1, $users);
        $user = $users[0];
        $this->assertSame(self::USERNAME, $user->getUsername());
        $this->assertTrue($user->isNewPasswordRequired());
        $this->assertTrue($user->hasRole('ROLE_USER'));

        $initialPassword = self::USERNAME . '-03-02-2001';
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($user, $initialPassword));

        $latest = $this->latestAccountsFor($user);
        $this->assertCount(1, $latest);
        $this->assertSame($initialPassword, $latest[0]->getPassword());
    }

    /** @dataProvider takenHandles */
    public function test_taken_username_creates_no_account(string $username, ?string $loginUsername, bool $deleted): void
    {
        $this->otherUser($username, $loginUsername, $deleted);
        $latestBefore = $this->latestAccountCount();

        $this->saveAttribution();

        $this->assertSame([], $this->usersOfMembre());
        $this->assertSame($latestBefore, $this->latestAccountCount());
        $this->assertNotEmpty($this->requestStack->getSession()->getFlashBag()->peek('warning'));
    }

    public function takenHandles(): array
    {
        return [
            'username of another user'              => [self::USERNAME, null, false],
            'login username of another user'        => ['tierthree-other', self::USERNAME, false],
            'username of a soft-deleted user'       => [self::USERNAME, null, true],
        ];
    }

    public function test_taken_username_outside_a_request_does_not_fail_the_save(): void
    {
        // Attributions are also saved from commands / workers, where there is no session.
        $this->requestStack = new RequestStack();
        $this->otherUser(self::USERNAME);

        $this->saveAttribution();

        $this->assertSame([], $this->usersOfMembre());
    }

    /** @dataProvider existingAccounts */
    public function test_membre_with_an_account_gets_no_second_one(bool $deleted): void
    {
        $this->otherUser('tierthree-own', deleted: $deleted, linked: true);

        $this->saveAttribution();

        $users = $this->usersOfMembre();
        $this->assertCount(1, $users);
        $this->assertSame('tierthree-own', $users[0]->getUsername());
    }

    public function existingAccounts(): array
    {
        return ['active account' => [false], 'soft-deleted account' => [true]];
    }
}
