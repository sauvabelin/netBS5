<?php

namespace NetBS\CoreBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\AuditLog;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class SoftDeletedEntityListener
{
    private EntityManagerInterface $em;
    private AuthorizationCheckerInterface $authChecker;
    private Environment $twig;
    private array $routeMap;

    public function __construct(
        EntityManagerInterface $em,
        AuthorizationCheckerInterface $authChecker,
        Environment $twig,
        FichierConfig $config
    ) {
        $this->em = $em;
        $this->authChecker = $authChecker;
        $this->twig = $twig;

        $this->routeMap = [
            'netbs.fichier.membre.page_membre'   => ['class' => $config->getMembreClass(), 'type' => 'Membre'],
            'netbs.fichier.famille.page_famille'  => ['class' => $config->getFamilleClass(), 'type' => 'Famille'],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        if (!$this->authChecker->isGranted('ROLE_SG')) {
            return;
        }

        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');

        if (!isset($this->routeMap[$routeName])) {
            return;
        }

        // If anything goes wrong rendering the restore page, let the 404 propagate
        try {
            $this->handleSoftDeletedEntity($event, $routeName);
        } catch (\Throwable $e) {
            return;
        }
    }

    private function handleSoftDeletedEntity(ExceptionEvent $event, string $routeName): void
    {
        $entityClass = $this->routeMap[$routeName]['class'];
        $entityType = $this->routeMap[$routeName]['type'];
        $id = (int) $event->getRequest()->attributes->get('id');

        if (!$id) {
            return;
        }

        $filters = $this->em->getFilters();
        $filterWasEnabled = $filters->isEnabled('softdeleteable');
        if ($filterWasEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            $entity = $this->em->find($entityClass, $id);
        } finally {
            if ($filterWasEnabled) {
                $filters->enable('softdeleteable');
            }
        }

        if (!$entity || $entity->getDeletedAt() === null) {
            return;
        }

        $log = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->where('a.action = :action')
            ->andWhere('a.entityClass = :class')
            ->andWhere('a.entityId = :id')
            ->setParameter('action', AuditLog::ACTION_DELETE)
            ->setParameter('class', $entityClass)
            ->setParameter('id', $id)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $deletedBy = ($log && $log->getUser()) ? (string) $log->getUser() : null;

        $bannerHtml = $this->twig->render('@NetBSCore/trash/restore_banner.html.twig', [
            'entity'     => $entity,
            'entityType' => $entityType,
            'deletedBy'  => $deletedBy,
        ]);

        $html = $this->twig->render('@NetBSCore/trash/deleted_entity_page.html.twig', [
            'title'  => (string) $entity,
            'banner' => $bannerHtml,
            'entity' => $entity,
        ]);

        $event->setResponse(new Response($html, Response::HTTP_GONE));
    }
}
