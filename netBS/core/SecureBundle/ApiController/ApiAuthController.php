<?php

namespace NetBS\SecureBundle\ApiController;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ApiAuthController extends AbstractController
{
    /**
     * @Route("/gettoken", name="netbs_secure_api_gettoken", methods={"POST"})
     */
    public function getTokenAction()
    {
        $user = $this->getUser();
        return new JsonResponse();
    }
}
