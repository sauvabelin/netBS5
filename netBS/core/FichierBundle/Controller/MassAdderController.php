<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Controller\MassUpdaterController;
use NetBS\CoreBundle\Service\History;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\CoreBundle\Service\MassUpdaterManager;
use NetBS\FichierBundle\Service\FichierConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class MassUpdaterController
 * @Route("/mass")
 */
class MassAdderController extends MassUpdaterController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/adder", name="netbs.fichier.mass.add")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function dataCreateAction(Request $request, ListBridgeManager $bridges, MassUpdaterManager $mass, EntityManagerInterface $em, History $history) {
        if($request->getMethod() !== 'POST') {

            $this->addFlash('warning', "Opération  d'ajout interrompue, veuillez réessayer.");
            return $this->redirectToRoute('netbs.core.home.dashboard');
        }

        $data       = json_decode($request->get('data'), true);
        $items      = [];


        if($request->get('form') === null) {

            $updatedClass   = $data[self::CLASS_KEY];

            if($updatedClass === 'attribution')        $updatedClass = $this->config->getAttributionClass();
            elseif($updatedClass === 'distinction')    $updatedClass = $this->config->getObtentionDistinctionClass();
            else throw $this->createAccessDeniedException();

            $ownerClass = base64_decode($data['ownerClass']);
            $ownerIds   = $data['ownerIds'];

            $owners     = $this->getMassItems($ownerClass, $ownerIds);
            $membres    = $bridges->convertItems($owners, $this->config->getMembreClass());

            foreach($membres as $membre) {

                $item = new $updatedClass();
                $item->setMembre($membre);
                $items[] = $item;
            }
        }

        else {
            $updatedClass   = base64_decode($request->get('form')[self::CLASS_KEY]);
        }

        $formData   = [
            'items'         => $items,
            'updatedClass'  => base64_encode($updatedClass)
        ];

        return $this->handleUpdater($request, $formData, $mass->getUpdaterForClass($updatedClass), $em, $history);
    }
}
