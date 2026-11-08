<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\BSMembre;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Entity\Famille;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A user may see their own membre and famille, but deleting them is a
 * secrétariat (ROLE_SG) operation, also through the generic remove endpoint.
 */
final class SelfMembreDeleteTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private BSMembre $membre;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $famille = new Famille();
        $famille->setNom('SelfDelete');
        $this->membre = new BSMembre();
        $this->membre->setPrenom('Self');
        $this->membre->setSexe('m');
        $this->membre->setStatut(BSMembre::INSCRIT);
        $this->membre->setNaissance(new \DateTime('2000-01-01'));
        $this->membre->setInscription(new \DateTime('2020-01-01'));
        $this->membre->setFamille($famille);
        $this->em->persist($famille);
        $this->em->persist($this->membre);
        $this->em->flush();

        $user = $this->persistUser($this->client, 'self-delete-user', roles: ['ROLE_USER']);
        $user->setMembre($this->membre);
        $this->em->flush();
        $this->client->loginUser($user, 'netbs');
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement("DELETE l FROM netbs_core_audit_log l JOIN sauvabelin_netbs_users u ON u.id = l.user_id WHERE u.username = 'self-delete-user'");
        $db->executeStatement("DELETE FROM sauvabelin_netbs_users WHERE username = 'self-delete-user'");
        $db->executeStatement("DELETE m FROM sauvabelin_netbs_membres m JOIN netbs_fichier_familles f ON f.id = m.famille_id WHERE f.nom = 'SelfDelete'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = 'SelfDelete'");
    }

    private function removeItem(object $item): void
    {
        $this->client->request('POST', sprintf('/netBS/app/actions/remove-item/%d/%s', $item->getId(), base64_encode(get_class($item))));
    }

    public function test_user_cannot_delete_their_own_membre(): void
    {
        $this->removeItem($this->membre);

        $this->assertResponseStatusCodeSame(403);
        $this->em->clear();
        $this->assertNotNull($this->em->find(BSMembre::class, $this->membre->getId()));
    }

    public function test_user_cannot_delete_their_own_famille(): void
    {
        $this->removeItem($this->membre->getFamille());

        $this->assertResponseStatusCodeSame(403);
        $this->em->clear();
        $this->assertNotNull($this->em->find(Famille::class, $this->membre->getFamille()->getId()));
    }
}
