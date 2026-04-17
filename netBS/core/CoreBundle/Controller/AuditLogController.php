<?php

namespace NetBS\CoreBundle\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/audit')]
class AuditLogController extends AbstractController
{
    #[Route('/list', name: 'netbs.core.audit_log.list')]
    #[IsGranted('ROLE_SG')]
    public function listAction()
    {
        return $this->render('@NetBSCore/audit_log/list.html.twig');
    }
}
