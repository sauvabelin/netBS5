<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\InMemoryFactureTrait;
use Ovesco\FacturationBundle\Entity\Rappel;
use PHPUnit\Framework\TestCase;

/** What adding a rappel (RappelController) does to a facture's print state. */
final class FactureRappelTest extends TestCase
{
    use InMemoryFactureTrait;

    private function addRappel($facture, string $date): Rappel
    {
        $rappel = new Rappel();
        $rappel->setDate(new \DateTime($date));
        $facture->addRappel($rappel);

        return $rappel;
    }

    public function test_new_rappel_makes_a_printed_facture_unprinted_again(): void
    {
        $facture = $this->facture(printed: true);

        $this->addRappel($facture, '2026-04-01');

        $this->assertFalse($facture->hasBeenPrinted());
        $this->assertCount(1, $facture->getRappels());
    }

    public function test_printing_after_a_rappel_stamps_the_rappel_not_the_facture(): void
    {
        $facture = $this->facture(printed: true);
        $originalPrint = $facture->getDateImpression();
        $rappel = $this->addRappel($facture, '2026-04-01');

        $facture->setLatestImpression(new \DateTime('2026-04-05'));

        $this->assertSame('2026-04-05', $rappel->getDateImpression()->format('Y-m-d'));
        $this->assertSame($originalPrint, $facture->getDateImpression());
        $this->assertSame('2026-04-05', $facture->getLatestImpression()->format('Y-m-d'));
        $this->assertTrue($facture->hasBeenPrinted());
    }

    public function test_unprinted_rappel_keeps_the_original_print_date_as_latest(): void
    {
        $facture = $this->facture(printed: true);

        $this->addRappel($facture, '2026-04-01');

        $this->assertSame('2026-03-10', $facture->getLatestImpression()->format('Y-m-d'));
    }

    public function test_second_rappel_is_stamped_on_the_next_print(): void
    {
        $facture = $this->facture(printed: true);
        $first = $this->addRappel($facture, '2026-04-01');
        $facture->setLatestImpression(new \DateTime('2026-04-05'));
        $second = $this->addRappel($facture, '2026-05-01');

        $facture->setLatestImpression(new \DateTime('2026-05-05'));

        $this->assertSame('2026-04-05', $first->getDateImpression()->format('Y-m-d'));
        $this->assertSame('2026-05-05', $second->getDateImpression()->format('Y-m-d'));
        $this->assertCount(2, $facture->getRappels());
    }
}
