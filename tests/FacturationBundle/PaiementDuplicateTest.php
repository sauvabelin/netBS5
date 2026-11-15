<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use Genkgo\Camt\DTO\EntryTransactionDetail;
use Genkgo\Camt\DTO\Reference;
use Ovesco\FacturationBundle\Entity\Paiement;
use PHPUnit\Framework\TestCase;

/** CAMT import uses this to skip a transaction that is already booked. */
final class PaiementDuplicateTest extends TestCase
{
    private function paiement(float $montant, string $date, ?string $instructionId = null): Paiement
    {
        $detail = new EntryTransactionDetail();
        if ($instructionId !== null) {
            $detail->setReference((new Reference())->setInstructionId($instructionId));
        }

        return (new Paiement())
            ->setMontant($montant)
            ->setDate(new \DateTime($date))
            ->setTransactionDetails($detail);
    }

    public function test_same_instruction_id_is_a_duplicate(): void
    {
        $this->assertTrue($this->paiement(50, '2026-03-01', 'A1')->isSamePaymentAs($this->paiement(50, '2026-03-02', 'A1')));
    }

    public function test_different_instruction_ids_are_distinct_even_with_same_amount_and_date(): void
    {
        $this->assertFalse($this->paiement(50, '2026-03-01', 'A1')->isSamePaymentAs($this->paiement(50, '2026-03-01', 'B2')));
    }

    public function test_without_instruction_ids_same_amount_and_date_is_a_duplicate(): void
    {
        $this->assertTrue($this->paiement(50, '2026-03-01')->isSamePaymentAs($this->paiement(50, '2026-03-01')));
    }

    public function test_without_instruction_ids_different_amount_is_distinct(): void
    {
        $this->assertFalse($this->paiement(50, '2026-03-01')->isSamePaymentAs($this->paiement(40, '2026-03-01')));
    }

    public function test_without_instruction_ids_different_date_is_distinct(): void
    {
        $this->assertFalse($this->paiement(50, '2026-03-01')->isSamePaymentAs($this->paiement(50, '2026-03-05')));
    }

    public function test_manual_paiement_without_transaction_details_compares_amount_and_date(): void
    {
        $manual = (new Paiement())->setMontant(50)->setDate(new \DateTime('2026-03-01'));

        $this->assertTrue($manual->isSamePaymentAs($this->paiement(50, '2026-03-01', 'A1')));
    }
}
