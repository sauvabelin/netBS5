<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\LoggedChange;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Route('/changelog')]
class ChangelogController extends AbstractController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/list', name: 'netbs.core.changelog.list')]
    #[IsGranted('ROLE_SG')]
    public function lookupChangesAction() {
        return $this->render('@NetBSCore/changelog/list_changes.html.twig');
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/approve', name: 'netbs.core.changelog.approve')]
    #[IsGranted('ROLE_SG')]
    public function approveChangesAction(Request $request, EntityManagerInterface $em) {

        $data       = json_decode($request->request->get('data'), true);

        $changes    = $em->createQueryBuilder()->select('c')
            ->from(LoggedChange::class, 'c')
            ->where('c.id IN(:ids)')
            ->setParameter('ids', $data['selectedIds'])
            ->getQuery()
            ->execute();

        /** @var LoggedChange[] $changes */
        foreach($changes as $change)
            $change->setStatus(LoggedChange::APPROVED);

        $em->flush();

        $this->addFlash('success', count($changes) . " modifications approuvées");
        return $this->redirectToRoute('netbs.core.changelog.list');
    }

    /**
     * @param Request $request
     * @internal param LoggedChange $change
     * @return Response
     */
    #[Route('ajax/preview', name: 'netbs.core.changelog.preview_change')]
    #[IsGranted('ROLE_SG')]
    public function ajaxPreviewChangeAction(Request $request, EntityManagerInterface $em) {

        $id     = $request->get('logId');
        $change = $em->find(LoggedChange::class, $id);

        if(!$change)
            throw $this->createNotFoundException();

        return $this->render('@NetBSCore/changelog/diff.ajax.twig', [
            'log'       => $change
        ]);
    }
}
