<?php

declare(strict_types=1);

namespace App\Ldap\Controller;

use App\Entity\BSGroupe;
use App\Ldap\DirectoryNotConfiguredException;
use App\Ldap\DirectoryProjection;
use App\Ldap\DirectorySync;
use App\Ldap\LldapClientException;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration › Authentication › LDAP: shows what the directory sync would
 * produce (same projection as `netbs:ldap:sync`, dry-run against lldap) and
 * lets an admin apply it right away. The group rules themselves are edited on
 * Structure › Types de groupes and Fonctions.
 */
#[AsController]
#[Route('/ldap')]
final class LdapAdminController extends AbstractController
{
    /** Errors shown as toasts after "sync now"; the rest are summarised. */
    private const MAX_ERROR_FLASHES = 5;

    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectorySync $sync,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'auth.admin.ldap.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $snapshot = $this->projection->snapshot();
        } catch (DirectoryNotConfiguredException $e) {
            return $this->render('ldap/admin.html.twig', ['notConfigured' => $e->getMessage()]);
        }

        $report = null;
        $lldapError = null;
        try {
            $report = $this->sync->sync($snapshot['users'], $snapshot['groups'], true);
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP preview: lldap unreachable', ['exception' => $e]);
            $lldapError = $e->getMessage();
        }

        /** @var array<int, BSGroupe> $groupes */
        $groupes = [];
        foreach ($this->em->getRepository(BSGroupe::class)->findBy(['id' => array_keys($snapshot['groups'])]) as $groupe) {
            $groupes[$groupe->getId()] = $groupe;
        }

        $groups = $snapshot['groups'];
        uasort($groups, fn ($a, $b) => strcasecmp($a->name, $b->name));

        return $this->render('ldap/admin.html.twig', [
            'notConfigured' => null,
            'users'         => $snapshot['users'],
            'groups'        => $groups,
            'groupes'       => $groupes,
            'report'        => $report,
            'lldapError'    => $lldapError,
            'sousGroupesLabels' => array_flip(BaseGroupeType::getSousGroupesChoices()),
        ]);
    }

    #[Route('/sync', name: 'auth.admin.ldap.sync', methods: ['POST'])]
    public function syncNow(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('ldap_sync', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $snapshot = $this->projection->snapshot();
            $report = $this->sync->sync($snapshot['users'], $snapshot['groups'], false);
        } catch (DirectoryNotConfiguredException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('auth.admin.ldap.index');
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP sync now: lldap unreachable', ['exception' => $e]);
            $this->addFlash('error', 'Synchronisation impossible : lldap ne répond pas.');
            return $this->redirectToRoute('auth.admin.ldap.index');
        }

        if ($report->errors === []) {
            $this->addFlash('success', sprintf('Synchronisation terminée : %d changement(s).', count($report->changes)));
        } else {
            $this->logger->warning('LDAP sync now finished with errors', ['errors' => $report->errors]);
            $this->addFlash('warning', sprintf('Synchronisation terminée : %d changement(s), %d erreur(s).', count($report->changes), count($report->errors)));
            foreach (array_slice($report->errors, 0, self::MAX_ERROR_FLASHES) as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('auth.admin.ldap.index');
    }
}
