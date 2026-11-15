<?php

namespace Ovesco\FacturationBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use Genkgo\Camt\DTO\Record;
use Genkgo\Camt\Reader;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Model\ParsedCamt;

/**
 * Books the transactions of a CAMT file as paiements on the factures their QR
 * reference points to. Persists the new paiements; the caller flushes.
 */
class CamtImporter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @throws \Exception when the file is unreadable or names an unknown compte */
    public function import(string $path): ParsedCamt
    {
        $parsedCamt = new ParsedCamt();
        $reader = new Reader(Config::getDefault());

        foreach ($reader->readFile($path)->getRecords() as $statement) {
            foreach ($statement->getEntries() as $entry) {
                foreach ($entry->getTransactionDetails() as $transactionDetail) {
                    $this->importTransaction($parsedCamt, $statement, $entry, $transactionDetail);
                }
            }
        }

        return $parsedCamt;
    }

    /**
     * The facture id encoded in a QR reference (as built by
     * QrPaymentReferenceGenerator): zero padding and the check digit removed.
     */
    public static function factureIdFromReference(string $reference): int
    {
        return intval(substr(ltrim($reference, '0'), 0, -1));
    }

    private function importTransaction(ParsedCamt $parsedCamt, Record $statement, Entry $entry, EntryTransactionDetail $transactionDetail): void
    {
        $compte = $this->findCompte($entry->getReference() ?? $statement->getAccount()->getIdentification());

        $facture = null;
        $creditorReference = $this->getRemittanceInformation($transactionDetail)?->getCreditorReferenceInformation();
        if ($creditorReference) {
            $facture = $this->em->getRepository(Facture::class)
                ->findByFactureId(self::factureIdFromReference($creditorReference->getRef()));
        }

        $paiement = $this->transactionToPaiement($transactionDetail, $entry);
        $paiement->setCompte($compte);

        if (!$facture) {
            $parsedCamt->addOrphanPaiement($paiement);
            return;
        }

        // Re-imported file: this transaction is already booked on the facture
        foreach ($facture->getPaiements() as $existing) {
            if ($existing->isSamePaymentAs($paiement)) {
                $parsedCamt->addDoublePaiement($facture);
                return;
            }
        }

        $wasPaid = $facture->getStatut() === Facture::PAYEE;
        $this->em->persist($paiement);
        $facture->addPaiement($paiement);

        if ($wasPaid) {
            $parsedCamt->addAlreadyPaid($facture);
        } elseif ($facture->getStatut() === Facture::PAYEE) {
            $parsedCamt->addFacture($facture);
        } else {
            $parsedCamt->addNotEnough($facture);
        }
    }

    private function findCompte(string $account): Compte
    {
        $comptes = $this->em->getRepository(Compte::class)->createQueryBuilder('c')
            ->where("REPLACE(c.qrIban, ' ', '') = REPLACE(:acc, ' ', '')")
            ->orWhere("REPLACE(c.iban, ' ', '') = REPLACE(:acc, ' ', '')")
            ->orWhere("REPLACE(c.ccp, '-', '') = REPLACE(:acc, ' ', '')")
            ->setParameter('acc', $account)
            ->getQuery()->getResult();

        if (count($comptes) !== 1) {
            throw new \Exception("Aucun compte enregistré pour le CCP " . $account);
        }

        return $comptes[0];
    }

    private function getRemittanceInformation(EntryTransactionDetail $detail)
    {
        try {
            return $detail->getRemittanceInformation();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function transactionToPaiement(EntryTransactionDetail $transactionDetail, Entry $entry): Paiement
    {
        $date = $transactionDetail->getRelatedDates()
            ? $transactionDetail->getRelatedDates()->getAcceptanceDateTime()
            : $entry->getValueDate();

        $paiement = (new Paiement())
            ->setMontant($transactionDetail->getAmount()->getAmount() / 100)
            ->setDate($date)
            ->setTransactionDetails($transactionDetail);

        $remittance = $this->getRemittanceInformation($transactionDetail);
        if ($remittance) {
            $paiement->setRemarques($remittance->getMessage());
        }

        return $paiement;
    }
}
