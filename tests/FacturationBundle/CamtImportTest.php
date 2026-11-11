<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Model\ParsedCamt;
use Ovesco\FacturationBundle\Service\CamtImporter;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Booking the transactions of a camt.054 file on the factures they reference. */
final class CamtImportTest extends KernelTestCase
{
    private const QR_IBAN   = 'CH4431999123000889012';
    private const MAIN_IBAN = 'CH9300762011623852957';
    private const MARKER  = 'CamtImportTest';

    private EntityManagerInterface $em;
    private Famille $famille;
    /** @var string[] */
    private array $files = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->cleanUp();

        $compte = new Compte();
        $compte->setNom(self::MARKER);
        // Stored with spaces, sent without: every booking test also covers the IBAN match.
        // The bank names the credited QR-IBAN in NtryRef; Acct is the main IBAN.
        $compte->setQrIban('CH44 3199 9123 0008 8901 2');
        $compte->setIban(self::MAIN_IBAN);
        $compte->setCcp('99-999999-9');
        $this->famille = new Famille();
        $this->famille->setNom(self::MARKER);
        $this->em->persist($compte);
        $this->em->persist($this->famille);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $db = $this->em->getConnection();
        $factures = "SELECT f.id FROM ovesco_facturation_factures f JOIN netbs_fichier_familles fa ON f.debiteur_id = CONCAT('famille:', fa.id) WHERE fa.nom = '" . self::MARKER . "'";
        $db->executeStatement("DELETE FROM ovesco_facturation_paiements WHERE facture_id IN ($factures)");
        $db->executeStatement("DELETE p FROM ovesco_facturation_paiements p JOIN ovesco_facturation_comptes c ON c.id = p.compte_id WHERE c.nom = '" . self::MARKER . "'");
        $db->executeStatement("DELETE FROM ovesco_facturation_creances WHERE facture_id IN ($factures)");
        $db->executeStatement("DELETE FROM ovesco_facturation_factures WHERE id IN (SELECT id FROM ($factures) AS ids)");
        $db->executeStatement("DELETE FROM ovesco_facturation_comptes WHERE nom = '" . self::MARKER . "'");
        $db->executeStatement("DELETE FROM netbs_fichier_familles WHERE nom = '" . self::MARKER . "'");
    }

    private function persistFacture(float $montant, string $statut = Facture::OUVERTE): int
    {
        $facture = new Facture();
        $facture->setDebiteur($this->famille);
        $creance = (new Creance())->setTitre('Cotisation')->setMontant($montant);
        $creance->setDebiteur($this->famille);
        $facture->addCreance($creance);
        $facture->setStatut($statut);
        $this->em->persist($facture);
        $this->em->flush();

        return $facture->getId();
    }

    private function fill(string $fixture, array $values): string
    {
        $xml = file_get_contents(__DIR__ . '/fixtures/' . $fixture);
        foreach ($values as $key => $value) {
            $xml = str_replace('{{' . $key . '}}', (string) $value, $xml);
        }

        return $xml;
    }

    private function writeTemp(string $xml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'camt') . '.xml';
        file_put_contents($path, $xml);
        $this->files[] = $path;

        return $path;
    }

    /** A QR transaction paying $amount on facture $factureId. */
    private function qrTransaction(int $factureId, string $amount, string $instructionId = 'INSTR-1'): array
    {
        return ['camt054_tx_qrr.xml', [
            'AMOUNT'         => $amount,
            'REFERENCE'      => QrPaymentReferenceGenerator::generate(null, (string) $factureId),
            'INSTRUCTION_ID' => $instructionId,
        ]];
    }

    /** One batch credit (Sammelgutschrift) on $account holding $transactions, like PostFinance sends. */
    private function notification(array $transactions, string $account = self::QR_IBAN): string
    {
        $common = ['ACCOUNT' => $account, 'DATE' => '2026-03-02'];
        $xml = '';
        $total = 0;
        foreach ($transactions as [$fixture, $values]) {
            $xml .= $this->fill($fixture, $values + $common);
            $total += (float) $values['AMOUNT'];
        }

        return $this->writeTemp($this->fill('camt054_qr_notification.xml', $common + [
            'MAIN_IBAN'    => self::MAIN_IBAN,
            'TOTAL'        => number_format($total, 2, '.', ''),
            'COUNT'        => count($transactions),
            'TRANSACTIONS' => $xml,
        ]));
    }

    private function payFacture(int $factureId, string $amount, string $instructionId = 'INSTR-1', string $account = self::QR_IBAN): ParsedCamt
    {
        return $this->import($this->notification([$this->qrTransaction($factureId, $amount, $instructionId)], $account));
    }

    private function import(string $path): ParsedCamt
    {
        $result = static::getContainer()->get(CamtImporter::class)->import($path);
        $this->em->flush();

        return $result;
    }

    private function reload(int $factureId): Facture
    {
        $this->em->clear();

        return $this->em->find(Facture::class, $factureId);
    }

    public function test_qr_reference_decodes_back_to_the_facture_id(): void
    {
        foreach ([1, 20000, 30675, 1234567] as $id) {
            $this->assertSame($id, CamtImporter::factureIdFromReference(QrPaymentReferenceGenerator::generate(null, (string) $id)));
        }
    }

    public function test_full_payment_marks_the_facture_paid(): void
    {
        $id = $this->persistFacture(100);

        $result = $this->payFacture($id, '100.00');

        $this->assertCount(1, $result->getFactures());
        $facture = $this->reload($id);
        $this->assertSame(Facture::PAYEE, $facture->getStatut());
        $this->assertCount(1, $facture->getPaiements());
        $this->assertSame(self::MARKER, $facture->getPaiements()->first()->getCompte()->getNom());
    }

    public function test_amount_too_low_leaves_the_facture_open(): void
    {
        $id = $this->persistFacture(100);

        $result = $this->payFacture($id, '60.00');

        $this->assertCount(1, $result->getNotEnough());
        $facture = $this->reload($id);
        $this->assertSame(Facture::OUVERTE, $facture->getStatut());
        $this->assertEqualsWithDelta(40, $facture->getMontantEncoreDu(), 0.001);
    }

    public function test_second_installment_completes_the_payment(): void
    {
        $id = $this->persistFacture(200);
        $this->payFacture($id, '100.00', 'INSTR-1');

        $result = $this->payFacture($id, '100.00', 'INSTR-2');

        $this->assertCount(1, $result->getFactures());
        $this->assertSame(Facture::PAYEE, $this->reload($id)->getStatut());
    }

    public function test_new_payment_on_a_paid_facture_is_booked_as_already_paid(): void
    {
        $id = $this->persistFacture(100);
        $this->payFacture($id, '100.00', 'INSTR-1');

        $result = $this->payFacture($id, '100.00', 'INSTR-2');

        $this->assertCount(1, $result->getAlreadyPaid());
        $this->assertCount(2, $this->reload($id)->getPaiements());
    }

    public function test_reimporting_the_same_payment_is_a_double_paiement(): void
    {
        $id = $this->persistFacture(100);
        $this->payFacture($id, '60.00');

        $result = $this->payFacture($id, '60.00');

        $this->assertCount(1, $result->getDoublePaiements());
        $facture = $this->reload($id);
        $this->assertCount(1, $facture->getPaiements());
        $this->assertSame(Facture::OUVERTE, $facture->getStatut());
    }

    public function test_reimporting_a_payment_that_settled_the_facture_is_a_double_paiement(): void
    {
        $id = $this->persistFacture(100);
        $this->payFacture($id, '100.00');

        $result = $this->payFacture($id, '100.00');

        $this->assertCount(1, $result->getDoublePaiements());
        $this->assertCount(0, $result->getAlreadyPaid());
        $this->assertCount(1, $this->reload($id)->getPaiements());
    }

    public function test_reference_to_no_facture_is_an_orphan_paiement(): void
    {
        $id = $this->persistFacture(100);
        $this->em->getConnection()->executeStatement('DELETE FROM ovesco_facturation_creances WHERE facture_id = ?', [$id]);
        $this->em->getConnection()->executeStatement('DELETE FROM ovesco_facturation_factures WHERE id = ?', [$id]);
        $this->em->clear();

        $result = $this->payFacture($id, '100.00');

        $this->assertCount(1, $result->getOrphanPaiements());
        $this->assertCount(0, $result->getFactures());
    }

    public function test_payment_without_reference_is_an_orphan_paiement(): void
    {
        $result = $this->import($this->notification([['camt054_tx_unstructured.xml', ['AMOUNT' => '100.00', 'INSTRUCTION_ID' => 'INSTR-1']]]));

        $this->assertCount(1, $result->getOrphanPaiements());
    }

    public function test_batch_credit_books_each_transaction_on_its_facture(): void
    {
        $paid = $this->persistFacture(60);
        $partial = $this->persistFacture(200);

        $result = $this->import($this->notification([
            $this->qrTransaction($paid, '60.00', 'INSTR-1'),
            $this->qrTransaction($partial, '100.00', 'INSTR-2'),
        ]));

        $this->assertCount(1, $result->getFactures());
        $this->assertCount(1, $result->getNotEnough());
        $this->assertSame(Facture::PAYEE, $this->reload($paid)->getStatut());
        $this->assertEqualsWithDelta(100, $this->reload($partial)->getMontantEncoreDu(), 0.001);
    }

    public function test_postfinance_sample_file_is_read(): void
    {
        $compte = $this->em->getRepository(Compte::class)->findOneBy(['nom' => self::MARKER]);
        $compte->setQrIban('CH7730000001250094239'); // the QR-IBAN credited in the sample
        $this->em->flush();

        $result = $this->import(__DIR__ . '/fixtures/postfinance_camt054_qr_v2019.xml');

        // Its references point at no facture here, so both payments come back as orphans
        $amounts = array_map(fn($p) => $p->getMontant(), $result->getOrphanPaiements());
        $this->assertEqualsWithDelta([778.29, 913.93], $amounts, 0.001);
        $this->assertSame(['1006265-25bbb3b1a', '1005970-70a75515'], array_map(fn($p) => $p->getInstructionId(), $result->getOrphanPaiements()));
    }

    public function test_unknown_compte_rejects_the_file(): void
    {
        $id = $this->persistFacture(100);

        try {
            $this->payFacture($id, '100.00', account: 'CH5604835012345678009');
            $this->fail('Expected the import to be rejected');
        } catch (\Exception $e) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }

        $this->assertCount(0, $this->reload($id)->getPaiements());
    }
}
