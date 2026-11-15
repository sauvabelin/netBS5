<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Entity\BSMembre;
use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Entity\Famille;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Select2 search only returns items the user may READ. Usernames are the one
 * exception, so a dynamic list can be shared with another user.
 */
final class Select2AuthorizationTest extends WebTestCase
{
    use UserFactoryTrait;

    private const PRENOM = 'Selectdeuxsecret';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $famille = new Famille();
        $famille->setNom('Select2Auth');
        $membre = new BSMembre();
        $membre->setPrenom(self::PRENOM);
        $membre->setSexe('m');
        $membre->setStatut(BSMembre::INSCRIT);
        $membre->setNaissance(new \DateTime('2000-01-01'));
        $membre->setInscription(new \DateTime('2020-01-01'));
        $membre->setFamille($famille);
        $this->em->persist($famille);
        $this->em->persist($membre);
        $this->em->flush();

        $this->persistUser($this->client, 'select2-other-user', roles: ['ROLE_USER']);
        $this->client->loginUser($this->persistUser($this->client, 'select2-plain-user', roles: ['ROLE_USER']), 'netbs');
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement("DELETE l FROM netbs_core_audit_log l JOIN sauvabelin_netbs_users u ON u.id = l.user_id WHERE u.username LIKE 'select2-%'");
        $db->executeStatement("DELETE FROM sauvabelin_netbs_users WHERE username LIKE 'select2-%'");
        $db->executeStatement("DELETE m FROM sauvabelin_netbs_membres m JOIN netbs_fichier_familles f ON f.id = m.famille_id WHERE f.nom = 'Select2Auth'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = 'Select2Auth'");
    }

    private function search(string $class, string $query, array $headers = []): array
    {
        $this->client->request('GET', '/netBS/netbs/select2/results', [
            'ajaxClass' => base64_encode($class),
            'query'     => $query,
        ], [], $headers);
        $this->assertResponseIsSuccessful();

        return array_column(json_decode($this->client->getResponse()->getContent(), true)['results'], 'id');
    }

    public function test_unreadable_membre_is_hidden_even_with_a_dynamic_list_referer(): void
    {
        $this->assertSame([], $this->search(BSMembre::class, self::PRENOM));
        $this->assertSame([], $this->search(BSMembre::class, self::PRENOM, ['HTTP_REFERER' => 'http://netbs.localhost/netBS/core/dynamic-list/manage/1']));
    }

    public function test_other_users_can_be_found_to_share_a_list(): void
    {
        $other = $this->em->getRepository(BSUser::class)->findOneBy(['username' => 'select2-other-user']);

        $this->assertContains($other->getId(), $this->search(BSUser::class, 'select2-other'));
    }
}
