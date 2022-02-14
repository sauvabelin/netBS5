<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\Form\DynamicListType;
use NetBS\CoreBundle\Service\DynamicListManager;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DynamicListController
 * @Route("/dynamic-list")
 */
class DynamicListController extends AbstractController
{
    /**
     * @Route("/manage/lists", name="netbs.core.dynamics_list.manage_lists")
     */
    public function manageListsAction(DynamicListManager $dynamics) {

        return $this->render('@NetBSCore/dynamics/manage_dynamic_lists.html.twig', array(
            'lists' => $dynamics->getCurrentUserLists()
        ));
    }

    /**
     * @Route("/remove-items/{id}", name="netbs.core.dynamics_list.remove_items")
     * @param Request $request
     * @param DynamicList $list
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function removeElementFromListAction(Request $request, DynamicList $list, EntityManagerInterface $em) {

        $ids    = json_decode($request->get('data'), true)['removed_ids'];
        $ids    = array_map(function($id) {return intval($id);}, $ids);

        if(!$this->isGranted(CRUD::UPDATE, $list))
            throw $this->createAccessDeniedException();

        foreach($list->getItems() as $item)
            if(in_array($item->getId(), $ids))
                $list->removeItem($item);

        $em->persist($list);
        $em->flush();

        $this->addFlash("info", count($ids) . " éléments retirés de la liste");
        return $this->redirectToRoute('netbs.core.dynamics_list.manage_list', array('id' => $list->getId()));
    }

    /**
     * @Route("/remove/list/{id}", name="netbs.core.dynamics_list.remove_list")
     * @param DynamicList $list
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function removeListAction(DynamicList $list, EntityManagerInterface $em) {

        if(!$this->isGranted(CRUD::DELETE, $list))
            throw $this->createAccessDeniedException();

        $em->remove($list);
        $em->flush();

        return $this->redirectToRoute('netbs.core.dynamics_list.manage_lists');
    }

    /**
     * @Route("/manage/{id}", name="netbs.core.dynamics_list.manage_list")
     * @param DynamicList $list
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \NetBS\ListBundle\Exceptions\ListModelNotFoundException
     */
    public function manageListAction(DynamicList $list, DynamicListManager $manager) {

        if(!$this->isGranted(CRUD::READ, $list))
            throw $this->createAccessDeniedException();

        $model      = $manager->getModelForClass($list->getItemsClass());
        $form       = $this->createForm(DynamicListType::class, $list);

        return $this->render('@NetBSCore/dynamics/manage_dynamic_list.html.twig', array(
            'model'     => $model,
            'form'      => $form->createView(),
            'list'      => $list
        ));
    }

    /**
     * @Route("/items/direct-add", name="netbs.core.dynamics_list.items_add")
     * @param Request $request
     * @return DynamicList|null|object
     */
    public function addItemsToList(Request $request, DynamicListManager $dynamics, EntityManagerInterface $em) {

        $listId     = $request->get('listId');
        $listItems  = $request->get('selectedIds');
        $itemsClass = base64_decode($request->get('itemsClass'));

        return $this->successResponse($this->performListAddage($listId, $listItems, $itemsClass, $em, $dynamics));
    }

    protected function performListAddage($listId, $listItems, $itemsClass, $em, $dynamics) {
        $list       = $em->getRepository('NetBSCoreBundle:DynamicList')
            ->findOneBy(array(
                'owner' => $this->getUser(),
                'id'    => $listId
            ));

        if($list === null)
            throw $this->createNotFoundException("Aucune liste avec cet identifiant trouvé pour l'utilisateur courant!");

        if(!$this->isGranted(CRUD::UPDATE, $list))
            throw $this->createAccessDeniedException();

        if(is_array($listItems)) {

            foreach ($listItems as $itemId) {

                $item = $em->getRepository($itemsClass)->find($itemId);

                if($item !== NULL)
                    $dynamics->addItemToList($item, $list);
            }

            $em->persist($list);
            $em->flush();
        }

        return $list;

    }

    /**
     * @param Request $request
     * @Route("/modal/add-list", name="netbs.core.dynamic_list.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addListModalAction(Request $request, DynamicListManager $dynamics) {

        $encoded    = $request->request->get('itemClass');
        $class      = $encoded ? base64_decode($encoded) : null;

        $list       = new DynamicList();
        $form       = $this->createForm(DynamicListType::class, $list, [
            'itemClass' => $class,
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $list = $dynamics->saveNewList($form->getData());

            $this->addFlash("info", "Liste {$list->getName()} créée avec succès!");
            return $this->json([
                'listId' =>$list->getId(),
                'class'  => $list->getItemsClass()
            ], 202);
        }

        return $this->render('@NetBSCore/dynamics/create.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    protected function successResponse(DynamicList $list) {

        return $this->json([
            'id'    => $list->getId(),
            'name'  => $list->getName(),
            'count' => count($list->getItems())
        ]);
    }
}
