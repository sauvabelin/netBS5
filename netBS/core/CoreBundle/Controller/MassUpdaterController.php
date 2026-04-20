<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\CoreBundle\Service\History;
use NetBS\CoreBundle\Service\MassUpdaterManager;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MassUpdaterController
 */
#[Route('/mass-updater')]
class MassUpdaterController extends AbstractController
{
    const FORM_DATA   = 'data';
    const HOLDER_KEY  = 'holderClass';
    const CLASS_KEY   = 'updatedClass';
    const IDS_KEY     = 'updatedIds';

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/update-data', name: 'netbs.core.mass_updater.data_update')]
    #[IsGranted('ROLE_UPDATE_EVERYWHERE')]
    public function dataUpdateAction(Request $request, MassUpdaterManager $mass, EntityManagerInterface $em, History $history) {

        if($request->getMethod() !== 'POST') {

            $this->addFlash('warning', "Opération de modification interrompue, veuillez réessayer.");
            return $this->redirectToRoute('netbs.core.home.dashboard');
        }

        $class      = null;
        $ids        = null;

        if($request->get('form') === null) {

            $data   = json_decode($request->get(self::FORM_DATA), true);
            $class  = $data[self::CLASS_KEY];
            $ids    = $data[self::IDS_KEY];
        }

        else {

            $data   = $request->get('form');
            $class  = $data[self::CLASS_KEY];
            $ids    = isset($data['ids']) ? json_decode($data['ids']) : [];
        }

        $updater        = $mass->getUpdaterForClass(base64_decode($class));
        $items          = $this->getMassItems(base64_decode($class), $ids, $em);

        $data           = [
            'items'         => $items,
            'updatedClass'  => $data[self::CLASS_KEY],
            'ids'           => json_encode($ids)
        ];

        return $this->handleUpdater($request, $data, $updater, $em, $history);
    }

    /**
     * @param Request $request
     * @param array $data
     * @param BaseMassUpdater $updater
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    protected function handleUpdater(Request $request, array $data, BaseMassUpdater $updater, EntityManagerInterface $em, History $history, string $title = 'Mise à jour rapide') {

        $genericForm    = $this->createForm($updater->getItemForm());

        /** @var Form $massForm */
        $massForm       = $this->createFormBuilder($data)
            ->add('items', CollectionType::class, array(
                'allow_add'     => $updater->allowAdd(),
                'allow_delete'  => $updater->allowDelete(),
                'entry_type'    => $updater->getItemForm()
            ))
            ->add('updatedClass', HiddenType::class)
            ->add('ids', HiddenType::class)
            ->getForm();

        $returnUrl = $this->resolveSafeReturnUrl($request);

        $massForm->handleRequest($request);

        if($massForm->isSubmitted() && $massForm->isValid()) {

            $items  = $massForm->getData()['items'];
            foreach($items as $item)
                $em->persist($item);

            $em->flush();

            $this->addFlash('success', "Modifications enregistrées pour " . count($items) . " éléments");

            if ($returnUrl) {
                return new RedirectResponse($returnUrl);
            }
            return $history->getPreviousRoute(3);
        }

        $response = $this->render('@NetBSCore/updater/updater.html.twig', array(
            'title'         => $title,
            'form'          => $massForm->createView(),
            'showToString'  => $updater->showToString(),
            'generic'       => $genericForm->createView(),
            'skipFields'    => $updater->getSkipFields(),
            'returnUrl'     => $returnUrl,
        ));

        if ($massForm->isSubmitted()) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $response;
    }

    /**
     * Captures the page to return to after the mass-update form is submitted.
     *
     * On initial render the referer identifies the calling list; on resubmit
     * the original value is carried through the form's _return_url field.
     * Non-local URLs are discarded to prevent open-redirect attacks.
     */
    private function resolveSafeReturnUrl(Request $request): string
    {
        $returnUrl = $request->request->get('_return_url')
            ?: $request->headers->get('referer', '');

        if ($returnUrl && !$this->isLocalUrl($returnUrl, $request)) {
            return '';
        }

        return $returnUrl;
    }

    private function isLocalUrl(string $url, Request $request): bool
    {
        return str_starts_with($url, '/')
            || str_starts_with($url, $request->getSchemeAndHttpHost());
    }

    /**
     * @param $class
     * @param array $ids
     * @return array
     */
    public function getMassItems($class, array $ids, EntityManagerInterface $em) {
        $items      = $em->createQueryBuilder()
            ->select('x')
            ->from($class, 'x')
            ->where('x.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();

        return $items;
    }
}
