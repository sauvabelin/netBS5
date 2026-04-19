<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\AuditLog;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/trash')]
class TrashController extends AbstractController
{
    private array $entityTypes;

    public function __construct(FichierConfig $config, SecureConfig $secureConfig)
    {
        $this->entityTypes = [
            'Membre'      => $config->getMembreClass(),
            'Famille'     => $config->getFamilleClass(),
            'Attribution' => $config->getAttributionClass(),
            'Distinction' => $config->getObtentionDistinctionClass(),
            'Géniteur'    => $config->getGeniteurClass(),
            'Utilisateur' => $secureConfig->getUserClass(),
        ];
    }

    #[Route('/list', name: 'netbs.core.trash.list')]
    #[IsGranted('ROLE_SG')]
    public function listAction(Request $request, EntityManagerInterface $em): Response
    {
        $selectedType = $request->query->get('type', 'Membre');

        if (!isset($this->entityTypes[$selectedType])) {
            $selectedType = 'Membre';
        }

        $entityClass = $this->entityTypes[$selectedType];

        $deleted = [];
        $deletionInfo = [];

        $filters = $em->getFilters();
        $filterWasEnabled = $filters->isEnabled('softdeleteable');
        if ($filterWasEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            $deleted = $em->createQueryBuilder()
                ->select('e')
                ->from($entityClass, 'e')
                ->where('e.deletedAt IS NOT NULL')
                ->orderBy('e.deletedAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult();

            // Batch query for deletion info (avoids N+1)
            $deletionInfo = [];
            if (!empty($deleted)) {
                $ids = array_map(fn($e) => $e->getId(), $deleted);

                $logs = $em->createQueryBuilder()
                    ->select('a')
                    ->from(AuditLog::class, 'a')
                    ->where('a.action = :action')
                    ->andWhere('a.entityClass = :class')
                    ->andWhere('a.entityId IN (:ids)')
                    ->setParameter('action', AuditLog::ACTION_DELETE)
                    ->setParameter('class', $entityClass)
                    ->setParameter('ids', $ids)
                    ->getQuery()
                    ->getResult();

                foreach ($logs as $log) {
                    $eid = $log->getEntityId();
                    if (!isset($deletionInfo[$eid])
                        || $log->getCreatedAt() > $deletionInfo[$eid]->getCreatedAt()) {
                        $deletionInfo[$eid] = $log;
                    }
                }
            }

            // Render with filter still disabled — soft-deleted relations
            // (e.g., a soft-deleted Membre on an Attribution) need to be
            // accessible for __toString() during template rendering
            return $this->render('@NetBSCore/trash/list.html.twig', [
                'entityTypes'  => $this->entityTypes,
                'selectedType' => $selectedType,
                'deleted'      => $deleted,
                'deletionInfo' => $deletionInfo,
            ]);
        } finally {
            if ($filterWasEnabled) {
                $filters->enable('softdeleteable');
            }
        }
    }

    #[Route('/restore', name: 'netbs.core.trash.restore', methods: ['POST'])]
    #[IsGranted('ROLE_SG')]
    public function restoreAction(Request $request, EntityManagerInterface $em): Response
    {
        $type = $request->request->get('type', 'Membre');
        $entityId = (int) $request->request->get('entity_id');

        if (!isset($this->entityTypes[$type])) {
            throw $this->createNotFoundException('Type d\'entité non supporté');
        }

        $entityClass = $this->entityTypes[$type];

        $filters = $em->getFilters();
        $filterWasEnabled = $filters->isEnabled('softdeleteable');
        if ($filterWasEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            $entity = $em->find($entityClass, $entityId);
        } finally {
            if ($filterWasEnabled) {
                $filters->enable('softdeleteable');
            }
        }

        if (!$entity) {
            throw $this->createNotFoundException('Élément introuvable');
        }

        $entity->setDeletedAt(null);
        $em->flush();

        $this->addFlash('success', 'Élément restauré avec succès');

        return $this->redirectToRoute('netbs.core.trash.list', ['type' => $type]);
    }
}
