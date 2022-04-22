<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Genkgo\Camt\Config;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Model\ParsedBVR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CamtController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/camt")
 */
class CamtController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/import", name="ovesco.facturation.camt.import")
     */
    public function importAction(Request $request, EntityManagerInterface $em) {

        $parsedBVR = null;
        $form = $this->createFormBuilder([])->add('file', FileType::class, ['label' => 'Fichier BVR'])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $parsedBVR = $this->parseBVRFile($data['file'], $em);
                $em->flush();
                return $this->render('@OvescoFacturation/camt/result.html.twig', [
                    'result' => $parsedBVR,
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', "Fichier illisible: " . $e->getMessage());
                return $this->redirectToRoute('ovesco.facturation.camt.import');
            }
        }
        return $this->render('@OvescoFacturation/camt/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param UploadedFile $file
     * @return ParsedBVR
     * @throws \Exception
     */
    private function parseBVRFile(UploadedFile $file, EntityManagerInterface $em) {
        $parsedBVR = new ParsedBVR();
        $reader = new \Genkgo\Camt\Reader(Config::getDefault());
        $data = $reader->readFile($file);
        $statements = $data->getRecords();
        $factureRepo = $em->getRepository('OvescoFacturationBundle:Facture');

        foreach($statements as $statement) {
            foreach($statement->getEntries() as $entry) {

                foreach ($entry->getTransactionDetails() as $transactionDetail) {

                    $query = $em->getRepository('OvescoFacturationBundle:Compte')->createQueryBuilder('c');
                    $givenAccount = $entry->getReference();
                    if ($givenAccount === null) $givenAccount = $statement->getAccount()->getIdentification();
                    $compte = $query->where("REPLACE(c.qrIban, ' ', '') = REPLACE(:acc, ' ', '')")
                        ->orWhere("REPLACE(c.iban, ' ', '') = REPLACE(:acc, ' ', '')")
                        ->orWhere("REPLACE(c.ccp, '-', '') = REPLACE(:acc, ' ', '')")->setParameter('acc', $givenAccount)->getQuery()->getResult();
                    if (count($compte) !== 1) throw new \Exception("Aucun compte enregistré pour le CCP " . $entry->getReference());

                    /** @var Facture $facture */
                    $facture = null;
                    if ($this->getRemittanceInformation($transactionDetail) && $transactionDetail->getRemittanceInformation()->getCreditorReferenceInformation()) {
                        $refNumber  = $transactionDetail->getRemittanceInformation()->getCreditorReferenceInformation()->getRef(); //Get reference number
                        $refNumber  = ltrim($refNumber, 0); //Enlever tous les 0 de remplissage
                        $refNumber  = substr($refNumber, 0, -1); //Enlever la somme de contrôle
                        $factureId  = intval($refNumber);
                        $facture    = $factureRepo->findByFactureId($factureId);
                    }

                    $paiement = $this->transactionToPaiement($transactionDetail, $entry);
                    $paiement->setCompte($compte[0]);

                    if ($facture) {

                        $alreadyPaid = false;
                        $samePaiement = false;
                        // Check paiement à double
                        if (count($facture->getPaiements()) === 1) {

                            $p = $facture->getLatestPaiement();

                            $refPaiement = $transactionDetail?->getReference()?->getInstructionId();
                            $refExisting = $p->getTransactionDetails() ? $p->getTransactionDetails()->getReference()->getInstructionId() : null;

                            // mêmes refs de paiement
                            if ($refPaiement !== null && $refExisting !== null)
                                if ($refPaiement === $refExisting)
                                    $samePaiement = true;
                                else if($p->getMontant() === $paiement->getMontant() && $p->getDateEffectivePaiement()->getTimestamp() === $paiement->getDateEffectivePaiement()->getTimestamp())
                                    $samePaiement = true;
                        }

                        // facture déjà payée avant le paiement
                        if ($facture->getStatut() === Facture::PAYEE) {
                            if ($samePaiement) $parsedBVR->addDoublePaiement($facture);
                            else {
                                $em->persist($paiement);
                                $facture->addPaiement($paiement);
                                $parsedBVR->addAlreadyPaid($facture);
                            }
                        }

                        // facture pas encore payée
                        else {
                            // Paiement déjà enregistré
                            if ($samePaiement) {
                                $parsedBVR->addDoublePaiement($facture);
                            } // Normal
                            else {

                                $em->persist($paiement);
                                $facture->addPaiement($paiement);
                                if ($facture->getStatut() === Facture::PAYEE)
                                    $parsedBVR->addFacture($facture);
                                else
                                    $parsedBVR->addNotEnough($facture);
                            }
                        }
                    }
                    else {
                        $parsedBVR->addOrphanPaiement($paiement);
                    }
                }
            }
        }

        return $parsedBVR;
    }

    private function getRemittanceInformation(EntryTransactionDetail $detail) {
        try {
            return $detail->getRemittanceInformation();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function transactionToPaiement(EntryTransactionDetail $transactionDetail, Entry $entry) {

        $paiement   = new Paiement();
        $date = $transactionDetail->getRelatedDates()
            ? $transactionDetail->getRelatedDates()->getAcceptanceDateTime()
            : $entry->getValueDate();

        $paiement
            ->setMontant($transactionDetail->getAmount()->getAmount() / 100)
            ->setDate($date)
            ->setTransactionDetails($transactionDetail);

        $remitt = $this->getRemittanceInformation($transactionDetail);
        if ($remitt) $paiement->setRemarques($remitt->getMessage());

        return $paiement;
    }
}
