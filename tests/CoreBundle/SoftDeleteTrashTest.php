<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle;

use App\Entity\BSMembre;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\ContactInformation;
use NetBS\FichierBundle\Entity\Famille;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Soft delete cascades down the Fichier tree, and the trash restores what went together. */
final class SoftDeleteTrashTest extends WebTestCase
{
    use UserFactoryTrait;

    private const MARKER = 'SoftDeleteTrashTest';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $familleId;
    private int $membreId;
    private int $adresseId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $famille = new Famille();
        $famille->setNom(self::MARKER);
        $famille->setContactInformation(new ContactInformation());

        $membre = new BSMembre();
        $membre->setPrenom('Trash');
        $membre->setSexe('m');
        $membre->setStatut(BSMembre::INSCRIT);
        $membre->setNaissance(new \DateTime('2000-01-01'));
        $membre->setInscription(new \DateTime('2020-01-01'));
        $membre->setContactInformation(new ContactInformation());
        $adresse = new Adresse();
        $adresse->setRue('Rue de la Corbeille 1');
        $adresse->setNpa('1000');
        $adresse->setLocalite('Lausanne');
        $membre->addAdresse($adresse);
        $famille->addMembre($membre);

        $this->em->persist($famille);
        $this->em->flush();
        $this->familleId = $famille->getId();
        $this->membreId  = $membre->getId();
        $this->adresseId = $adresse->getId();
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement("DELETE l FROM netbs_core_audit_log l JOIN sauvabelin_netbs_users u ON u.id = l.user_id WHERE u.username LIKE 'trash-%'");
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $infos = "SELECT contactInformation_id FROM netbs_fichier_familles WHERE nom = '" . self::MARKER . "'
            UNION SELECT m.contactInformation_id FROM sauvabelin_netbs_membres m JOIN netbs_fichier_familles f ON f.id = m.famille_id WHERE f.nom = '" . self::MARKER . "'";
        $db->executeStatement("DELETE FROM netbs_fichier_adresses WHERE contactInformation_id IN (SELECT * FROM ($infos) AS i)");
        $db->executeStatement("DELETE FROM netbs_fichier_contact_informations WHERE id IN (SELECT * FROM ($infos) AS i)");
        $db->executeStatement("DELETE m FROM sauvabelin_netbs_membres m JOIN netbs_fichier_familles f ON f.id = m.famille_id WHERE f.nom = '" . self::MARKER . "'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = '" . self::MARKER . "'");
        $db->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array{famille: ?\DateTimeInterface, membre: ?\DateTimeInterface, adresse: ?\DateTimeInterface} */
    private function deletedAts(): array
    {
        $this->em->clear();
        $this->em->getFilters()->disable('softdeleteable');
        try {
            return [
                'famille' => $this->em->find(Famille::class, $this->familleId)->getDeletedAt(),
                'membre'  => $this->em->find(BSMembre::class, $this->membreId)->getDeletedAt(),
                'adresse' => $this->em->find(Adresse::class, $this->adresseId)->getDeletedAt(),
            ];
        } finally {
            $this->em->getFilters()->enable('softdeleteable');
            $this->em->clear();
        }
    }

    private function softDeleteFamille(): void
    {
        $this->em->remove($this->em->find(Famille::class, $this->familleId));
        $this->em->flush();
        $this->em->clear();
    }

    private function restore(string $type, int $id): void
    {
        $this->client->request('POST', '/netBS/trash/restore', ['type' => $type, 'entity_id' => $id]);
    }

    private function loginAs(array $roles): void
    {
        $user = $this->persistUser($this->client, 'trash-' . strtolower(implode('-', $roles)), roles: $roles);
        $this->client->loginUser($user, 'netbs');
    }

    public function test_deleting_a_famille_soft_deletes_its_membres_and_their_adresses(): void
    {
        $this->softDeleteFamille();

        $deleted = $this->deletedAts();
        $this->assertNotNull($deleted['famille']);
        $this->assertEquals($deleted['famille'], $deleted['membre']);
        $this->assertEquals($deleted['famille'], $deleted['adresse']);
    }

    public function test_soft_deleted_rows_are_hidden_from_normal_queries(): void
    {
        $this->softDeleteFamille();

        $this->assertNull($this->em->find(Famille::class, $this->familleId));
        $this->assertNull($this->em->find(BSMembre::class, $this->membreId));
        $this->assertSame([], $this->em->getRepository(BSMembre::class)->findBy(['prenom' => 'Trash', 'famille' => $this->familleId]));
    }

    public function test_restoring_a_famille_restores_what_was_deleted_with_it(): void
    {
        $this->softDeleteFamille();
        $this->loginAs(['ROLE_USER', 'ROLE_SG']);

        $this->restore('Famille', $this->familleId);

        $this->assertResponseRedirects();
        $this->assertSame(['famille' => null, 'membre' => null, 'adresse' => null], $this->deletedAts());
    }

    public function test_restoring_a_famille_leaves_a_membre_deleted_earlier_in_the_trash(): void
    {
        $membre = $this->em->find(BSMembre::class, $this->membreId);
        $membre->setDeletedAt(new \DateTime('-1 day'));
        $this->em->flush();
        $this->em->clear();
        $this->softDeleteFamille();
        $this->loginAs(['ROLE_USER', 'ROLE_SG']);

        $this->restore('Famille', $this->familleId);

        $deleted = $this->deletedAts();
        $this->assertNull($deleted['famille']);
        $this->assertNotNull($deleted['membre']);
        $this->assertNotNull($deleted['adresse']);
    }

    public function test_restoring_a_membre_whose_famille_is_still_deleted_is_refused(): void
    {
        $this->softDeleteFamille();
        $this->loginAs(['ROLE_USER', 'ROLE_SG']);

        $this->restore('Membre', $this->membreId);

        $this->assertNotNull($this->deletedAts()['membre']);
    }

    public function test_plain_user_cannot_list_the_trash(): void
    {
        $this->loginAs(['ROLE_USER']);

        $this->client->request('GET', '/netBS/trash/list');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_plain_user_cannot_restore(): void
    {
        $this->softDeleteFamille();
        $this->loginAs(['ROLE_USER']);

        $this->restore('Famille', $this->familleId);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->deletedAts()['famille']);
    }

    public function test_secretariat_can_list_the_trash(): void
    {
        $this->softDeleteFamille();
        $this->loginAs(['ROLE_USER', 'ROLE_SG']);

        $this->client->request('GET', '/netBS/trash/list?type=Famille');

        $this->assertResponseIsSuccessful();
    }
}
