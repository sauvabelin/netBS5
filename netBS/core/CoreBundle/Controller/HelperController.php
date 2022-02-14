<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Service\HelperManager;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class HelperController extends AbstractController
{
    /**
     * @Route("/helper/get-help", name="netbs.core.helper.get_help")
     * @param Request $request
     * @return JsonResponse
     */
    public function getHelpAction(Request $request, HelperManager $helperManager)
    {
        $class          = base64_decode($request->request->get('class'));
        $id             = $request->request->get('id');
        $item           = $this->getDoctrine()->getRepository($class)->find($id);

        if(!$item)
            throw $this->createNotFoundException("Object not found");

        if(!$this->isGranted(CRUD::READ, $item))
            throw $this->createAccessDeniedException();

        $helper         = $helperManager->getFor($class);
        $data           = [
            'content'   => $helper->render($item),
            'title'     => $helper->getRepresentation($item)
        ];

        return new JsonResponse($data);
    }
}
