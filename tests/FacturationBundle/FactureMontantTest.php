<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Entity\BSMembre;
use App\Tests\Support\InMemoryFichierTrait;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use PHPUnit\Framework\TestCase;

/** Amounts owed on a facture, and the statut that follows from its paiements. */
final class FactureMontantTest extends TestCase
{
    use InMemoryFichierTrait;

    /** A famille with $inscrits registered membres, returning the first one. */
    private function membreInFamille(int $inscrits, int $others = 0): BSMembre
    {
        $famille = new Famille();
        $this->setEntityId($famille, 1);
        $membres = [];
        for ($i = 0; $i < $inscrits + $others; $i++) {
            $membre = $this->membre(100 + $i);
            $membre->setStatut($i < $inscrits ? BSMembre::INSCRIT : BSMembre::DESINSCRIT);
            $famille->addMembre($membre);
            $membres[] = $membre;
        }

        return $membres[0];
    }

    private function factureFor(object $debiteur, Creance ...$creances): Facture
    {
        $facture = new Facture();
        $facture->setDebiteur($debiteur);
        foreach ($creances as $creance) {
            $facture->addCreance($creance);
        }

        return $facture;
    }

    private function creance(float $montant, float $rabais = 0, float $rabaisFamille = 0): Creance
    {
        return (new Creance())->setMontant($montant)->setRabais($rabais)->setRabaisIfInFamille($rabaisFamille);
    }

    private function paiement(float $montant): Paiement
    {
        return (new Paiement())->setMontant($montant);
    }

    public function test_montant_sums_the_creances(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(30), $this->creance(20));

        $this->assertEqualsWithDelta(50, $facture->getMontant(), 0.001);
    }

    public function test_rabais_is_a_percentage_of_the_creance(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(80, 25));

        $this->assertEqualsWithDelta(60, $facture->getMontant(), 0.001);
    }

    public function test_famille_rabais_applies_when_two_membres_are_inscrits(): void
    {
        $facture = $this->factureFor($this->membreInFamille(2), $this->creance(100, 10, 20));

        $this->assertEqualsWithDelta(70, $facture->getMontant(), 0.001);
    }

    public function test_famille_rabais_ignored_with_a_single_inscrit(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1, others: 2), $this->creance(100, 10, 20));

        $this->assertEqualsWithDelta(90, $facture->getMontant(), 0.001);
    }

    public function test_famille_debiteur_counts_its_own_membres(): void
    {
        $famille = $this->membreInFamille(2)->getFamille();
        $creance = $this->creance(100, 0, 50);
        $this->factureFor($famille, $creance);

        $this->assertTrue($creance->rabaisFamilleApplicable());
        $this->assertEqualsWithDelta(50, $creance->getActualMontant(), 0.001);
    }

    public function test_creance_without_facture_gets_no_famille_rabais(): void
    {
        $creance = $this->creance(100, 10, 20);

        $this->assertFalse($creance->rabaisFamilleApplicable());
        $this->assertEqualsWithDelta(90, $creance->getActualMontant(), 0.001);
    }

    public function test_partial_paiement_leaves_the_rest_due_and_facture_open(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));

        $facture->addPaiement($this->paiement(40));

        $this->assertEqualsWithDelta(60, $facture->getMontantEncoreDu(), 0.001);
        $this->assertSame(Facture::OUVERTE, $facture->getStatut());
    }

    public function test_full_paiement_marks_facture_paid(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));

        $facture->addPaiement($this->paiement(60));
        $facture->addPaiement($this->paiement(40));

        $this->assertSame(Facture::PAYEE, $facture->getStatut());
    }

    public function test_overpaiement_marks_facture_paid_with_negative_rest(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));

        $facture->addPaiement($this->paiement(120));

        $this->assertEqualsWithDelta(-20, $facture->getMontantEncoreDu(), 0.001);
        $this->assertSame(Facture::PAYEE, $facture->getStatut());
    }

    public function test_rabais_below_the_centime_does_not_keep_a_paid_facture_open(): void
    {
        // A one-third rabais on 2 × 60.- leaves 80.004 due; the debtor pays 80.-
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(60, 33.33), $this->creance(60, 33.33));

        $facture->addPaiement($this->paiement(80));

        $this->assertSame(Facture::PAYEE, $facture->getStatut());
        $this->assertEquals(0, $facture->getMontantEncoreDu());
    }

    public function test_removing_a_paiement_reopens_a_paid_facture(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));
        $paiement = $this->paiement(100);
        $facture->addPaiement($paiement);

        $facture->removePaiement($paiement);

        $this->assertSame(Facture::OUVERTE, $facture->getStatut());
    }

    public function test_removing_a_surplus_paiement_keeps_facture_paid(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));
        $facture->addPaiement($this->paiement(100));
        $surplus = $this->paiement(20);
        $facture->addPaiement($surplus);

        $facture->removePaiement($surplus);

        $this->assertSame(Facture::PAYEE, $facture->getStatut());
    }

    public function test_partial_paiement_leaves_a_cancelled_facture_cancelled(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));
        $facture->setStatut(Facture::ANNULEE);

        $facture->addPaiement($this->paiement(40));

        $this->assertSame(Facture::ANNULEE, $facture->getStatut());
    }

    public function test_removing_a_paiement_leaves_a_cancelled_facture_cancelled(): void
    {
        $facture = $this->factureFor($this->membreInFamille(1), $this->creance(100));
        $paiement = $this->paiement(40);
        $facture->addPaiement($paiement);
        $facture->setStatut(Facture::ANNULEE);

        $facture->removePaiement($paiement);

        $this->assertSame(Facture::ANNULEE, $facture->getStatut());
    }
}
