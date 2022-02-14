<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\CoreBundle\Service\History;
use NetBS\CoreBundle\Service\MassUpdaterManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MassUpdaterController
 * @Route("/mass-updater")
 */
class MassUpdaterController extends AbstractController
{
    const FORM_DATA   = 'data';
    const HOLDER_KEY  = 'holderClass';
    const CLASS_KEY   = 'updatedClass';
    const IDS_KEY     = 'updatedIds';

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Route("/update-data", name="netbs.core.mass_updater.data_update")
     * @Security("is_granted('ROLE_UPDATE_EVERYWHERE')")
     */
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
        $items          = $this->getMassItems(base64_decode($class), $ids);

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
     * @Security("is_granted('ROLE_UPDATE_EVERYWHERE')")
     */
    protected function handleUpdater(Request $request, array $data, BaseMassUpdater $updater, EntityManagerInterface $em, History $history) {

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

        $massForm->handleRequest($request);

        if($massForm->isSubmitted() && $massForm->isValid()) {

            $items  = $massForm->getData()['items'];
            foreach($items as $item)
                $em->persist($item);

            $em->flush();

            $this->addFlash('success', "Modifications enregistrées pour " . count($items) . " éléments");
            return $history->getPreviousRoute(3);
        }

        return $this->render('@NetBSCore/updater/updater.html.twig', array(
            'form'          => $massForm->createView(),
            'showToString'  => $updater->showToString(),
            'generic'       => $genericForm->createView()
        ));
    }

    /**
     * @param $class
     * @param array $ids
     * @return array
     */
    public function getMassItems($class, array $ids) {
        $items      = $this->getDoctrine()->getManager()->createQueryBuilder()
            ->select('x')
            ->from($class, 'x')
            ->where('x.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();

        return $items;
    }
}
