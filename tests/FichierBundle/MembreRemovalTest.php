<?php

declare(strict_types=1);

namespace App\Tests\FichierBundle;

use App\Entity\BSMembre;
use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Removing a membre or famille (secrétariat) is blocked by open factures, and
 * otherwise takes the linked netBS user and their personal data with it.
 */
final class MembreRemovalTest extends WebTestCase
{
    use UserFactoryTrait;

    private const MARKER = 'MembreRemovalTest';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $familleId;
    /** @var int[] */
    private array $membreIds = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $famille = new Famille();
        $famille->setNom(self::MARKER);
        foreach (['Alice', 'Bob'] as $prenom) {
            $membre = new BSMembre();
            $membre->setPrenom($prenom);
            $membre->setSexe('m');
            $membre->setStatut(BSMembre::INSCRIT);
            $membre->setNaissance(new \DateTime('2000-01-01'));
            $membre->setInscription(new \DateTime('2020-01-01'));
            $famille->addMembre($membre);
        }
        $this->em->persist($famille);
        $this->em->flush();
        $this->familleId = $famille->getId();
        foreach ($famille->getMembres() as $membre) {
            $this->membreIds[] = $membre->getId();
        }

        foreach ($this->membreIds as $i => $membreId) {
            $user = $this->persistUser($this->client, "removal-linked-$i", roles: ['ROLE_USER']);
            $user->setMembre($this->em->find(BSMembre::class, $membreId));
            $this->ownList($user, self::MARKER . "-linked-$i");
        }
        $this->ownList($this->persistUser($this->client, 'removal-other', roles: ['ROLE_USER']), self::MARKER . '-other');
        $this->em->flush();

        $this->client->loginUser($this->persistUser($this->client, 'removal-sg', roles: ['ROLE_USER', 'ROLE_SG']), 'netbs');
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $m = self::MARKER;
        $debiteurs = "SELECT CONCAT('famille:', id) FROM netbs_fichier_familles WHERE nom = '$m'
            UNION SELECT CONCAT('membre:', mb.id) FROM sauvabelin_netbs_membres mb JOIN netbs_fichier_familles f ON f.id = mb.famille_id WHERE f.nom = '$m'";
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $db->executeStatement("DELETE FROM ovesco_facturation_factures WHERE debiteur_id IN (SELECT * FROM ($debiteurs) AS d)");
        $db->executeStatement("DELETE FROM netbs_core_dynamic_lists WHERE name LIKE '$m%'");
        $db->executeStatement("DELETE l FROM netbs_core_audit_log l JOIN sauvabelin_netbs_users u ON u.id = l.user_id WHERE u.username LIKE 'removal-%'");
        $db->executeStatement("DELETE FROM sauvabelin_netbs_users WHERE username LIKE 'removal-%'");
        $db->executeStatement("DELETE mb FROM sauvabelin_netbs_membres mb JOIN netbs_fichier_familles f ON f.id = mb.famille_id WHERE f.nom = '$m'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = '$m'");
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ownList(BSUser $user, string $name): void
    {
        $list = new DynamicList();
        $list->setName($name);
        $list->setItemsClass(BSMembre::class);
        $list->setOwner($user);
        $this->em->persist($list);
    }

    private function openFactureFor(object $debiteur): void
    {
        $facture = new Facture();
        $facture->setDebiteur($debiteur);
        $this->em->persist($facture);
        $this->em->flush();
    }

    private function membre(int $index): ?BSMembre
    {
        return $this->em->find(BSMembre::class, $this->membreIds[$index]);
    }

    private function removeMembre(int $index): void
    {
        $this->em->clear();
        $this->client->request('POST', '/netBS/fichier/membre/remove/' . $this->membreIds[$index]);
    }

    private function removeFamille(): void
    {
        $this->em->clear();
        $this->client->request('POST', '/netBS/fichier/famille/remove/' . $this->familleId);
    }

    private function userExists(string $username): bool
    {
        $this->em->clear();

        return $this->reloadUser($this->client, $username) !== null;
    }

    private function listExists(string $name): bool
    {
        return $this->em->getRepository(DynamicList::class)->findOneBy(['name' => $name]) !== null;
    }

    private function membreExists(int $index): bool
    {
        $this->em->clear();

        return $this->membre($index) !== null;
    }

    public function test_membre_with_an_open_facture_is_kept_with_its_user(): void
    {
        $this->openFactureFor($this->membre(0));

        $this->removeMembre(0);

        $this->assertTrue($this->membreExists(0));
        $this->assertTrue($this->userExists('removal-linked-0'));
        $this->assertTrue($this->listExists(self::MARKER . '-linked-0'));
    }

    public function test_membre_with_a_paid_facture_can_be_removed(): void
    {
        $facture = new Facture();
        $facture->setDebiteur($this->membre(0));
        $facture->setStatut(Facture::PAYEE);
        $this->em->persist($facture);
        $this->em->flush();

        $this->removeMembre(0);

        $this->assertFalse($this->membreExists(0));
    }

    public function test_removing_a_membre_takes_its_user_and_their_lists_and_nothing_else(): void
    {
        $this->removeMembre(0);

        $this->assertResponseRedirects();
        $this->assertFalse($this->membreExists(0));
        $this->assertFalse($this->userExists('removal-linked-0'));
        $this->assertFalse($this->listExists(self::MARKER . '-linked-0'));

        $this->assertTrue($this->membreExists(1));
        $this->assertTrue($this->userExists('removal-linked-1'));
        $this->assertTrue($this->listExists(self::MARKER . '-linked-1'));
        $this->assertTrue($this->listExists(self::MARKER . '-other'));
        $this->assertNotNull($this->em->find(Famille::class, $this->familleId));
    }

    public function test_famille_with_an_open_facture_is_kept_with_its_membres_and_users(): void
    {
        $this->openFactureFor($this->em->find(Famille::class, $this->familleId));

        $this->removeFamille();

        $this->assertNotNull($this->em->find(Famille::class, $this->familleId));
        $this->assertTrue($this->membreExists(0));
        $this->assertTrue($this->userExists('removal-linked-0'));
        $this->assertTrue($this->userExists('removal-linked-1'));
    }

    public function test_famille_whose_second_membre_has_an_open_facture_is_kept_whole(): void
    {
        $this->openFactureFor($this->membre(1));

        $this->removeFamille();

        $this->assertTrue($this->membreExists(0));
        $this->assertTrue($this->membreExists(1));
        $this->assertTrue($this->userExists('removal-linked-0'));
        $this->assertTrue($this->listExists(self::MARKER . '-linked-0'));
    }

    public function test_removing_a_famille_takes_its_membres_and_their_users(): void
    {
        $this->removeFamille();

        $this->em->clear();
        $this->assertNull($this->em->find(Famille::class, $this->familleId));
        $this->assertFalse($this->membreExists(0));
        $this->assertFalse($this->membreExists(1));
        $this->assertFalse($this->userExists('removal-linked-0'));
        $this->assertFalse($this->userExists('removal-linked-1'));
        $this->assertTrue($this->userExists('removal-other'));
    }

    public function test_removing_an_unknown_membre_is_a_404(): void
    {
        $this->client->request('POST', '/netBS/fichier/membre/remove/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function test_removing_an_unknown_famille_is_a_404(): void
    {
        $this->client->request('POST', '/netBS/fichier/famille/remove/999999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
