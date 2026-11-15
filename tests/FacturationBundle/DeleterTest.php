<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\InMemoryFactureTrait;
use Doctrine\ORM\EntityManager;
use Ovesco\FacturationBundle\Deleter\CreanceDeleter;
use Ovesco\FacturationBundle\Deleter\FactureDeleter;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use PHPUnit\Framework\TestCase;

/** Deleting créances and factures may never lose a printed or paid invoice. */
final class DeleterTest extends TestCase
{
    use InMemoryFactureTrait;

    /** @var object[] */
    private array $removed = [];

    private function manager(object $found): EntityManager
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('find')->willReturn($found);
        $em->method('remove')->willReturnCallback(function (object $entity) {
            $this->removed[] = $entity;
        });

        return $em;
    }

    private function deleteCreance(Creance $creance): void
    {
        $deleter = new CreanceDeleter();
        $deleter->setManager($this->manager($creance));
        $deleter->remove(1);
    }

    private function deleteFacture(Facture $facture): void
    {
        $deleter = new FactureDeleter();
        $deleter->setManager($this->manager($facture));
        $deleter->remove(1);
    }

    private function assertRefused(callable $delete): void
    {
        try {
            $delete();
        } catch (\Exception) {
            $this->assertSame([], $this->removed);
            return;
        }
        $this->fail('Deletion should have been refused');
    }

    public function test_loose_creance_is_deleted(): void
    {
        $creance = (new Creance())->setMontant(60);

        $this->deleteCreance($creance);

        $this->assertSame([$creance], $this->removed);
    }

    public function test_creance_of_an_open_unprinted_facture_is_deleted_and_the_facture_kept(): void
    {
        $facture = $this->facture(creances: 2);
        $creance = $facture->getCreances()->first();

        $this->deleteCreance($creance);

        $this->assertSame([$creance], $this->removed);
        $this->assertCount(1, $facture->getCreances());
    }

    public function test_last_creance_takes_its_empty_facture_with_it(): void
    {
        $facture = $this->facture(creances: 1);
        $creance = $facture->getCreances()->first();

        $this->deleteCreance($creance);

        $this->assertContains($facture, $this->removed);
        $this->assertContains($creance, $this->removed);
    }

    public function test_creance_of_a_printed_facture_is_refused(): void
    {
        $facture = $this->facture(creances: 2, printed: true);

        $this->assertRefused(fn() => $this->deleteCreance($facture->getCreances()->first()));
        $this->assertCount(2, $facture->getCreances());
    }

    /** @dataProvider closedStatuts */
    public function test_creance_of_a_closed_facture_is_refused(string $statut): void
    {
        $facture = $this->facture(creances: 2);
        $facture->setStatut($statut);

        $this->assertRefused(fn() => $this->deleteCreance($facture->getCreances()->first()));
    }

    public function test_open_unprinted_facture_is_deleted_with_its_creances(): void
    {
        $facture = $this->facture(creances: 2);

        $this->deleteFacture($facture);

        $this->assertContains($facture, $this->removed);
        $this->assertCount(3, $this->removed);
    }

    public function test_printed_facture_is_refused(): void
    {
        $this->assertRefused(fn() => $this->deleteFacture($this->facture(printed: true)));
    }

    /** @dataProvider closedStatuts */
    public function test_closed_facture_is_refused(string $statut): void
    {
        $facture = $this->facture();
        $facture->setStatut($statut);

        $this->assertRefused(fn() => $this->deleteFacture($facture));
    }

    public function test_facture_with_a_partial_paiement_is_refused(): void
    {
        $facture = $this->facture();
        $facture->getPaiements()->add((new Paiement())->setMontant(5));

        $this->assertRefused(fn() => $this->deleteFacture($facture));
    }

    public static function closedStatuts(): array
    {
        return [Facture::PAYEE => [Facture::PAYEE], Facture::ANNULEE => [Facture::ANNULEE]];
    }
}
