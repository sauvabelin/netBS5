<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Form\MassCreanceType;
use Ovesco\FacturationBundle\Form\MergeCreancesToFactureType;
use Ovesco\FacturationBundle\Model\MassCreances;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\ListBundle\Service\ListManager;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Model\MergeCreancesToFacture;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreanceController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/creances")
 */
class CreanceController extends AbstractController
{
    /**
     * @Route("/search", name="ovesco.facturation.search_creances")
     */
    public function searchCreanceAction(SearcherManager $searcher) {
        $instance = $searcher->bind(Creance::class);
        return $searcher->render($instance);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     * @Route("/modal-add", name="ovesco.facturation.creance.modal_add_many")
     */
    public function modalAddManyCreancesAction(Request $request, ListBridgeManager $bridges, EntityManagerInterface $em) {

        $mass = new MassCreances();
        $mass->setItemsClass($request->request->get('itemsClass'));
        if ($request->request->get('selectedIds'))
            $mass->setSelectedIds(serialize($request->request->get('selectedIds')));

        $form = $this->createForm(MassCreanceType::class, $mass);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $selectedIds    = unserialize($mass->getSelectedIds());
            $class          = base64_decode($mass->getItemsClass());
            $items          = [];

            foreach($selectedIds as $selectedId)
                $items[]    = $em->find($class, $selectedId);

            foreach($items as $item) {

                $creance    = $mass->toCreance();
                $debiteur   = null;

                if ($item instanceof Facture) {
                    if ($item->getStatut() === Facture::OUVERTE) {
                        $creance->setFacture($item);
                        $debiteur = $item->getDebiteur();
                    }
                    else
                        throw new \Exception("Vous essayez d'ajouter une créance à une facture fermée, la [{$item->getFactureId()}]");

                } else {
                    $debiteur = $bridges->convertItem($item, AdressableInterface::class);
                    if (!$debiteur) throw new \Exception("Erreur fatale, impossible de convertir " . $item->__toString() . " en débiteur");
                }

                $creance->setDebiteur($debiteur);
                $creance->setTitre($this->parseTitle($creance->getTitre(), $debiteur));
                $em->persist($creance);
            }

            $em->flush();
            $this->addFlash("success", count($selectedIds) . " créances ajoutées");

            return Modal::refresh();
        }

        return $this->render('@OvescoFacturation/creance/add_creance.modal.twig', [
            'form'      => $form->createView(),
            'toFact'    => base64_decode($mass->getItemsClass()) === Facture::class,
        ], Modal::renderModal($form));
    }

    /**
     * @param Request $request
     * @Route("/check-merge", name="ovesco.facturation.creance.check_merge")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function checkMergeCreances(Request $request, EntityManagerInterface $em) {

        $data = json_decode($request->request->get('data'), true);
        $ids = $data['creanceIds'];
        $creances = $this->extractCreances($ids, $em);
        $references = $this->generatePack($creances);

        return $this->render('@OvescoFacturation/facture/generator.html.twig', [
            'pack' => $references,
            'creances' => $creances
        ]);
    }

    /**
     * @param Request $request
     * @Route("/merge", name="ovesco.facturation.creance.merge")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function mergeCreances(Request $request, EntityManagerInterface $em) {

        $merger = new MergeCreancesToFacture();
        $merger->setCreanceIds(serialize($request->get('creanceIds')));
        $form = $this->createForm(MergeCreancesToFactureType::class, $merger);
        $form->handleRequest($request);

        $creanceIds = unserialize($merger->getCreanceIds());
        $creances = $this->extractCreances($creanceIds, $em);
        try {
            $factures = $this->generatePack($creances);

            if ($form->isSubmitted() && $form->isValid()) {

                /** @var Facture $facture */
                foreach ($factures as $facture) {
                    $facture->setCompteToUse($merger->getCompteToUse());
                    $facture->setRemarques($merger->getRemarques());
                    $em->persist($facture);
                }
                $em->flush();
                return Modal::ack(count($factures) . " factures ont été générées, veuillez éventuellement rafraichir la page");
            }
        } catch (\Exception $e) {
            return Modal::ack($e->getMessage(), 'error');
        }

        return $this->render('@OvescoFacturation/facture/merge_creances_to_facture.modal.twig', [
            'form'      => $form->createView(),
            'creances'  => $creances,
            'factures'  => $factures,
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/find-ouvertes", name="ovesco.facturation.creance.find_ouvertes")
     * @param SearcherManager $searcher
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function creancesOuvertesAction() {
        return $this->render("@OvescoFacturation/creance/creances_ouvertes.html.twig");
    }

    private function extractCreances($ids, EntityManagerInterface $em) {

        $query = $em->getRepository('OvescoFacturationBundle:Creance')->createQueryBuilder('c');
        return $query->where($query->expr()->in('c.id', $ids))->getQuery()->getResult();
    }

    /**
     * @param Creance[] $creances
     * @return array
     * @throws \Exception
     */
    private function generatePack($creances) {

        $references = [];
        /* Les créances ayant la même adresse de débiteur sont réunies, une facture par entrée du tableau */
        /** @var Creance $creance */
        foreach($creances as $creance) {
            if ($creance->getFacture() !== null)
                throw $this->createAccessDeniedException("La créance " . $creance . " est déjà associée à une facture!");
            $adresse = $creance->getDebiteur()->getSendableAdresse();
            if (!$adresse) throw new \Exception("Debiteur " . $creance->getDebiteur() . " n'a pas d'adresse!");
            if (!in_array($adresse->getId(), array_keys($references))) {
                $facture = new Facture();
                $facture->setDebiteur($adresse->getOwner());
                $references[$adresse->getId()] = $facture;
            }
            $references[$adresse->getId()]->addCreance($creance);
        }

        return $references;
    }

    private function parseTitle($title, $debiteur) {
        if (!$debiteur) return $title;
        $famille = $debiteur instanceof BaseMembre ? $debiteur->getFamille() : $debiteur;

        if ($debiteur instanceof BaseMembre)
            $title = str_replace('[PRENOM]', $debiteur->getPrenom(), $title);
        $title = str_replace('[NOM]', $famille->getNom(), $title);
        return $title;
    }
}
