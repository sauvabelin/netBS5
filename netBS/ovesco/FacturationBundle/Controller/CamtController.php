<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Ovesco\FacturationBundle\Service\CamtImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CamtController
 * @package Ovesco\FacturationBundle\Controller
 */
#[Route('/camt')]
class CamtController extends AbstractController
{
    /**
     * @param Request $request
     */
    #[Route('/import', name: 'ovesco.facturation.camt.import')]
    public function importAction(Request $request, EntityManagerInterface $em, CamtImporter $importer) {

        $form = $this->createFormBuilder([])->add('file', FileType::class, ['label' => 'Fichier CAMT'])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $parsedCamt = $importer->import($data['file']->getPathname());
                $em->flush();
                return $this->render('@OvescoFacturation/camt/result.html.twig', [
                    'result' => $parsedCamt,
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
}
