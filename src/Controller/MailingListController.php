<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\CoreBundle\Service\LoaderManager;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/netBS/mailing-list')]
class MailingListController extends AbstractController
{
    #[Route('/emails', name: 'netbs.mailing_list.emails', methods: ['POST'])]
    public function getEmailsAction(
        Request $request,
        EntityManagerInterface $em,
        FichierConfig $fichierConfig,
        SecureConfig $secureConfig,
        LoaderManager $loaderManager,
        ListBridgeManager $listBridgeManager
    ): Response {
        $selectedIds = $request->request->all('selectedIds');
        $type = $request->request->get('type', '');
        $itemsClass = base64_decode($request->request->get('itemsClass', ''));

        $emails = [];

        if (!empty($selectedIds) && in_array($type, ['parents', 'chefs'])) {
            $membres = $this->loadMembres($itemsClass, $selectedIds, $em, $loaderManager, $listBridgeManager, $fichierConfig);

            $emails = $type === 'parents'
                ? $this->collectParentEmails($membres)
                : $this->collectChefEmails($membres, $em, $secureConfig);

            $emails = array_values(array_unique(array_filter($emails)));
        }

        return $this->render('mailing/emails.modal.twig', [
            'type' => $type,
            'emails' => $emails,
        ], new Response(null, Response::HTTP_OK));
    }

    /**
     * @return BaseMembre[]
     */
    private function loadMembres(
        string $itemsClass,
        array $ids,
        EntityManagerInterface $em,
        LoaderManager $loaderManager,
        ListBridgeManager $listBridgeManager,
        FichierConfig $fichierConfig
    ): array {
        if ($loaderManager->hasLoader($itemsClass)) {
            $loader = $loaderManager->getLoader($itemsClass);
            $elements = array_map(fn($id) => $loader->fromId($id), $ids);
        } else {
            $qb = $em->createQueryBuilder();
            $elements = $qb->select('x')
                ->from($itemsClass, 'x')
                ->where($qb->expr()->in('x.id', ':ids'))
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();
        }

        $membreClass = $fichierConfig->getMembreClass();
        return $listBridgeManager->convertItems($elements, $membreClass);
    }

    /**
     * @param BaseMembre[] $membres
     * @return string[]
     */
    private function collectParentEmails(array $membres): array
    {
        $emails = [];

        foreach ($membres as $membre) {
            $sendable = $membre->getSendableEmail();
            if ($sendable) {
                $emails[] = $sendable->getEmail();
            }
        }

        return $emails;
    }

    /**
     * @param BaseMembre[] $membres
     * @return string[]
     */
    private function collectChefEmails(array $membres, EntityManagerInterface $em, SecureConfig $secureConfig): array
    {
        $emails = [];
        $userClass = $secureConfig->getUserClass();
        $userRepo = $em->getRepository($userClass);

        foreach ($membres as $membre) {
            $user = $userRepo->findOneBy(['membre' => $membre]);
            if ($user instanceof BaseUser && $user->getEmail()) {
                $emails[] = $user->getEmail();
            }
        }

        return $emails;
    }
}
