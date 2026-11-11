<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use NetBS\FichierBundle\Entity\Famille as BSFamille;
use App\Entity\BSMembre;
use App\Entity\BSUser;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** The "copy emails" modal takes ids from the client: it must only reveal membres the user may read. */
final class MailingListEmailsAuthorizationTest extends WebTestCase
{
    use UserFactoryTrait;

    private const CHEF_EMAIL = 'mailing-authz-chef@example.test';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ?int $familleId = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function tearDown(): void
    {
        $this->em->getFilters()->disable('softdeleteable');
        $this->em->createQuery('DELETE FROM ' . BSUser::class . ' u WHERE u.username = :u')->setParameter('u', 'mailing-authz-chef')->execute();
        if ($this->familleId !== null) {
            $this->em->createQuery('DELETE FROM ' . BSMembre::class . ' m WHERE m.famille = :f')->setParameter('f', $this->familleId)->execute();
            $this->em->createQuery('DELETE FROM ' . BSFamille::class . ' f WHERE f.id = :f')->setParameter('f', $this->familleId)->execute();
        }
        parent::tearDown();
    }

    /** A membre with no attribution, linked to an account carrying CHEF_EMAIL. */
    private function persistChef(): int
    {
        $famille = new BSFamille();
        $famille->setNom('MailingAuthz');
        $membre = new BSMembre();
        $membre->setPrenom('Chef');
        $membre->setSexe('m');
        $membre->setStatut(BSMembre::INSCRIT);
        $membre->setNaissance(new \DateTime('2000-01-01'));
        $membre->setInscription(new \DateTime('2020-01-01'));
        $membre->setFamille($famille); // also copies the famille's nom onto the membre
        $this->em->persist($famille);
        $this->em->persist($membre);
        $this->em->flush();
        $this->familleId = $famille->getId();

        $user = $this->persistUser($this->client, 'mailing-authz-chef', email: self::CHEF_EMAIL);
        $user->setMembre($this->em->find(BSMembre::class, $membre->getId()));
        $this->em->flush();

        return $membre->getId();
    }

    private function requestChefEmails(int $membreId): string
    {
        $this->client->request('POST', '/netBS/bs/netBS/mailing-list/emails', [
            'selectedIds' => [$membreId],
            'type'        => 'chefs',
            'itemsClass'  => base64_encode(BSMembre::class),
        ]);
        $this->assertResponseIsSuccessful();

        return (string) $this->client->getResponse()->getContent();
    }

    public function test_plain_user_gets_no_email_of_a_membre_they_cannot_read(): void
    {
        $membreId = $this->persistChef();
        $this->client->loginUser($this->persistUser($this->client, 'mailing-authz-plain', roles: ['ROLE_USER']), 'netbs');

        $this->assertStringNotContainsString(self::CHEF_EMAIL, $this->requestChefEmails($membreId));
    }

    public function test_user_who_can_read_everything_gets_the_email(): void
    {
        $membreId = $this->persistChef();
        $this->client->loginUser($this->persistUser($this->client, 'mailing-authz-admin', roles: ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');

        $this->assertStringContainsString(self::CHEF_EMAIL, $this->requestChefEmails($membreId));
    }
}
