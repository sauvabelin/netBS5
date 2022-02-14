<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Service\AutomaticListsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class DynamicListController
 * @Route("/automatic-list")
 */
class AutomaticListController extends AbstractController
{
    /**
     * @Route("/view/lists", name="netbs.core.automatic_list.view_lists")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     */
    public function viewListsAction(AutomaticListsManager $automatics) {
        return $this->render('@NetBSCore/automatics/view_automatics.page.twig', array(
            'models'    => $automatics->getAutomatics()
        ));
    }

    /**
     * @Route("/view/{alias}", name="netbs.core.automatic_list.view_list")
     * @param Request $request
     * @param $alias
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewListAction($alias, Request $request, AutomaticListsManager $manager) {

        $model  = $manager->getAutomaticByAlias($alias);
        $form   = null;

        if (!$model->isAllowed($this->getUser()))
            throw $this->createAccessDeniedException("Pas autorisé à utiliser cette liste!");

        if($model instanceof ConfigurableAutomaticInterface) {

            $data   = $model->buildDataHolder();
            $form   = $this->createFormBuilder($data);
            $model->buildForm($form);
            $form   = $form->getForm();

            $form->handleRequest($request);

            if($form->isSubmitted() && $form->isValid())
                $model->_setAutomaticData($form->getData());

            $form = $form->createView();
        }

        return $this->render('@NetBSCore/automatics/view_automatic.html.twig', array(
            'model' => $model,
            'form'  => $form
        ));
    }
}
