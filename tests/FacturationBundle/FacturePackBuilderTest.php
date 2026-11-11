<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Entity\BSMembre;
use App\Tests\Support\InMemoryFactureTrait;
use App\Tests\Support\InMemoryFichierTrait;
use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\ContactInformation;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Service\FacturePackBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Merging loose créances into factures (check-merge and merge). */
final class FacturePackBuilderTest extends TestCase
{
    use InMemoryFichierTrait;
    use InMemoryFactureTrait;

    private int $nextId = 1;

    private function famille(bool $withAdresse = true): Famille
    {
        $famille = new Famille();
        $this->setEntityId($famille, $this->nextId++);
        $famille->setContactInformation(new ContactInformation());
        if ($withAdresse) {
            $famille->addAdresse($this->adresse());
        }

        return $famille;
    }

    private function adresse(): Adresse
    {
        $adresse = new Adresse();
        $this->setEntityId($adresse, $this->nextId++);
        $adresse->setExpediable(true);
        $adresse->setRue('Chemin du Test ' . $adresse->getId());
        $adresse->setNpa('1000');
        $adresse->setLocalite('Lausanne');

        return $adresse;
    }

    private function membreOf(Famille $famille): BSMembre
    {
        $membre = $this->membre($this->nextId++);
        $membre->setContactInformation(new ContactInformation());
        $famille->addMembre($membre);

        return $membre;
    }

    private function creanceFor(object $debiteur, float $montant = 60): Creance
    {
        $creance = (new Creance())->setTitre('Cotisation')->setMontant($montant);
        $creance->setDebiteur($debiteur);

        return $creance;
    }

    public function test_creances_sharing_an_address_end_up_on_one_facture(): void
    {
        $famille = $this->famille();
        $creances = [
            $this->creanceFor($this->membreOf($famille)),
            $this->creanceFor($this->membreOf($famille)),
            $this->creanceFor($famille),
        ];

        $factures = (new FacturePackBuilder())->build($creances);

        $this->assertCount(1, $factures);
        $facture = reset($factures);
        $this->assertCount(3, $facture->getCreances());
        $this->assertSame($famille, $facture->getDebiteur());
    }

    public function test_each_address_gets_its_own_facture(): void
    {
        $a = $this->famille();
        $b = $this->famille();

        $factures = (new FacturePackBuilder())->build([
            $this->creanceFor($a), $this->creanceFor($b), $this->creanceFor($this->membreOf($a)),
        ]);

        $this->assertCount(2, $factures);
        $counts = array_map(fn($f) => count($f->getCreances()), array_values($factures));
        sort($counts);
        $this->assertSame([1, 2], $counts);
    }

    public function test_facture_goes_to_the_membre_who_owns_the_address(): void
    {
        $famille = $this->famille(withAdresse: false);
        $membre = $this->membreOf($famille);
        $membre->addAdresse($this->adresse());

        $factures = (new FacturePackBuilder())->build([$this->creanceFor($membre)]);

        $this->assertSame($membre, reset($factures)->getDebiteur());
    }

    public function test_creance_already_on_a_facture_is_refused(): void
    {
        $creance = $this->creanceFor($this->famille());
        $this->facture(creances: 0)->addCreance($creance);

        $this->expectException(AccessDeniedException::class);

        (new FacturePackBuilder())->build([$creance]);
    }

    public function test_debiteur_without_address_is_refused(): void
    {
        $famille = $this->famille(withAdresse: false);

        $this->expectException(\Exception::class);

        (new FacturePackBuilder())->build([$this->creanceFor($this->membreOf($famille))]);
    }
}
