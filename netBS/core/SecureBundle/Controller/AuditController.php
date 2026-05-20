<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\AccessAuditService;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AuditController extends AbstractController
{
    public function __construct(
        private readonly AccessAuditService $audit,
        private readonly EntityManagerInterface $em,
        private readonly SecureConfig $secureConfig,
        private readonly FichierConfig $fichierConfig,
    ) {}

    #[Route('/utilisateurs/audit', name: 'netbs.secure.audit.index')]
    public function index(): Response
    {
        return $this->render('@NetBSSecure/audit/index.html.twig', [
            'sensitive_roles' => AccessAuditService::SENSITIVE_ROLES,
        ]);
    }

    #[Route('/utilisateurs/audit/users/{id}', name: 'netbs.secure.audit.user_show')]
    public function userShow(int $id): Response
    {
        $user = $this->em->find($this->secureConfig->getUserClass(), $id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->render('@NetBSSecure/audit/user_detail.html.twig', [
            'report'          => $this->audit->auditUser($user),
            'sensitive_roles' => AccessAuditService::SENSITIVE_ROLES,
        ]);
    }

    #[Route('/utilisateurs/audit/scopes/groupe/{id}', name: 'netbs.secure.audit.scope_groupe')]
    public function scopeGroupe(int $id): Response
    {
        $groupe = $this->em->find($this->fichierConfig->getGroupeClass(), $id);
        if (!$groupe) {
            throw $this->createNotFoundException();
        }

        return $this->render('@NetBSSecure/audit/scope_detail.html.twig', [
            'report'          => $this->audit->auditScope($groupe),
            'sensitive_roles' => AccessAuditService::SENSITIVE_ROLES,
        ]);
    }

    #[Route('/utilisateurs/audit/scopes/role/{role}', name: 'netbs.secure.audit.scope_role', requirements: ['role' => 'ROLE_[A-Z_]+'])]
    public function scopeRole(string $role): Response
    {
        $roleEntity = $this->em->getRepository($this->secureConfig->getRoleClass())
            ->findOneBy(['role' => $role]);
        if (!$roleEntity) {
            throw $this->createNotFoundException();
        }

        return $this->render('@NetBSSecure/audit/scope_detail.html.twig', [
            'report'          => $this->audit->auditScope($roleEntity),
            'sensitive_roles' => AccessAuditService::SENSITIVE_ROLES,
        ]);
    }
}
