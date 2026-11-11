<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Facturation is a trésorier-only area, but its routes used to sit behind the
 * generic ^/netBS ROLE_USER rule only, and the generic delete/export endpoints
 * reached factures without asking the FacturationVoter.
 */
final class FacturationAuthorizationTest extends WebTestCase
{
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
    }

    private function loginAs(array $roles): void
    {
        $user = $this->persistUser($this->client, 'facturation-authz-' . strtolower(implode('-', $roles)), roles: $roles);
        $this->client->loginUser($user, 'netbs');
    }

    private function persistFacture(): int
    {
        $facture = new Facture();
        // No real debiteur needed: the postLoad subscriber tolerates a missing one.
        (new \ReflectionProperty(Facture::class, 'debiteurId'))->setValue($facture, 'famille:0');
        $this->em->persist($facture);
        $this->em->flush();
        $id = $facture->getId();
        $this->em->clear();

        return $id;
    }

    private function factureExists(int $id): bool
    {
        $this->em->clear();

        return $this->em->find(Facture::class, $id) !== null;
    }

    private function removeItem(int $id): void
    {
        $this->client->request('POST', sprintf('/netBS/app/actions/remove-item/%d/%s', $id, base64_encode(Facture::class)));
    }

    public function test_plain_user_cannot_open_facturation_pages(): void
    {
        $this->loginAs(['ROLE_USER']);

        $this->client->request('GET', '/netBS/facturation/dashboard');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_tresorier_can_open_facturation_pages(): void
    {
        $this->loginAs(['ROLE_USER', 'ROLE_TRESORIER']);

        $this->client->request('GET', '/netBS/facturation/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function test_plain_user_cannot_delete_a_facture(): void
    {
        $id = $this->persistFacture();
        $this->loginAs(['ROLE_USER']);

        $this->removeItem($id);

        $this->assertResponseStatusCodeSame(403);
        $this->assertTrue($this->factureExists($id));
    }

    public function test_tresorier_can_delete_a_facture(): void
    {
        $id = $this->persistFacture();
        $this->loginAs(['ROLE_USER', 'ROLE_TRESORIER']);

        $this->removeItem($id);

        $this->assertFalse($this->factureExists($id));
    }

    public function test_plain_user_export_skips_paiements_they_cannot_read(): void
    {
        $paiementId = $this->persistPaiement('authz-export-marker');
        $this->loginAs(['ROLE_USER']);

        $this->exportPaiement($paiementId);

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('authz-export-marker', $this->exportedContent());
    }

    public function test_tresorier_export_contains_the_paiement(): void
    {
        $paiementId = $this->persistPaiement('authz-export-marker');
        $this->loginAs(['ROLE_USER', 'ROLE_TRESORIER']);

        $this->exportPaiement($paiementId);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('authz-export-marker', $this->exportedContent());
    }

    private function persistPaiement(string $remarques): int
    {
        $facture = $this->em->find(Facture::class, $this->persistFacture());
        $paiement = (new Paiement())->setMontant(10)->setDate(new \DateTime())->setRemarques($remarques);
        $facture->addPaiement($paiement);
        $this->em->persist($paiement);
        $this->em->flush();
        $id = $paiement->getId();
        $this->em->clear();

        return $id;
    }

    private function exportPaiement(int $paiementId): void
    {
        $this->client->request('POST', '/netBS/export/export/selected', ['data' => json_encode([
            'exporterAlias' => 'facturation.csv.paiements_list',
            'itemsClass'    => base64_encode(Paiement::class),
            'selectedIds'   => [$paiementId],
        ])]);
    }

    private function exportedContent(): string
    {
        $response = $this->client->getResponse();
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return (string) file_get_contents($response->getFile()->getPathname());
        }

        return (string) $this->client->getInternalResponse()->getContent();
    }
}
