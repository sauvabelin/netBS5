<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\DeleterManager;
use NetBS\CoreBundle\Service\History;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActionController extends AbstractController
{
    /**
     * @param $itemId
     * @param $itemClass
     * @return Response
     * @Route("/app/actions/remove-item/{itemId}/{itemClass}", name="netbs.core.action.remove_item")
     */
    public function removeItemAction($itemId, $itemClass, DeleterManager $manager, EntityManagerInterface $em, History $history) {

        $itemClass  = base64_decode($itemClass);

        if($manager->getDeleter($itemClass)) {
            try {
                $msg = $manager->getDeleter($itemClass)->remove($itemId);
                $this->addFlash("info", is_string($msg) ? $msg : "élément supprimé avec succès");
            } catch (\Exception $e) {
                $this->addFlash('warning', $e->getMessage());
            }
        }

        else {
            $item = $em->find($itemClass, $itemId);

            if ($item) {

                if (!$this->isGranted(CRUD::DELETE, $item))
                    throw $this->createAccessDeniedException();

                $em->remove($item);
                $em->flush();

                $this->addFlash("info", "élément supprimé avec succès");
            }

            else{
                $this->addFlash("warning", "Une erreur s'est produite, action interrompue");
            }
        }

        return $history->getPreviousRoute();
    }
}
