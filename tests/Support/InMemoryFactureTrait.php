<?php

namespace App\Tests\Support;

use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Rappel;

/** Builds unsaved factures for rule and text tests. */
trait InMemoryFactureTrait
{
    protected function facture(int $rappels = 0, int $creances = 1, bool $printed = false, ?object $debiteur = null): Facture
    {
        $facture = new Facture();
        $facture->setDate(new \DateTime('2026-01-15'));
        for ($i = 0; $i < $creances; $i++) {
            $creance = new Creance();
            $creance->setTitre("Créance $i");
            $creance->setMontant(10);
            $facture->addCreance($creance);
        }
        for ($i = 0; $i < $rappels; $i++) {
            $rappel = new Rappel();
            $rappel->setDate(new \DateTime(sprintf('2026-0%d-01', $i + 2)));
            $facture->addRappel($rappel);
        }
        if ($printed) {
            $facture->setLatestImpression(new \DateTime('2026-03-10'));
        }
        if ($debiteur !== null) {
            $property = new \ReflectionProperty(Facture::class, 'debiteur');
            $property->setAccessible(true);
            $property->setValue($facture, $debiteur);
        }

        return $facture;
    }
}
