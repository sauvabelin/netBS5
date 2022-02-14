<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Block\LayoutManager;
use NetBS\CoreBundle\Block\Model\Tab;
use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Block\TabsCardBlock;
use NetBS\CoreBundle\Block\TemplateBlock;
use NetBS\CoreBundle\Event\RemoveFamilleEvent;
use NetBS\CoreBundle\Event\RemoveMembreEvent;
use NetBS\FichierBundle\Form\FamilleType;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MembreController
 * @Route("/famille")
 */
class FamilleController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    protected function fclass() {
        return $this->config->getFamilleClass();
    }

    /**
     * @Route("/page/{id}", name="netbs.fichier.famille.page_famille")
     * @param $id
     * @return Response
     */
    public function pageFamilleAction($id, EntityManagerInterface $em, LayoutManager $layout) {

        /** @var BaseFamille $famille */
        $famille    = $em->find($this->fclass(), $id);

        if(!$famille)
            throw $this->createNotFoundException("Aucune famille trouvée");

        if(!$this->isGranted(CRUD::READ, $famille))
            throw $this->createAccessDeniedException();

        $form       = $this->createForm(FamilleType::class, $famille)->createView();

        $config     = $layout::configurator()
            ->addRow()
                ->pushColumn(3)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(CardBlock::class, [
                                'template'  => "@NetBSFichier/famille/presentation.block.twig",
                                'title'     => $famille->__toString(),
                                'params'    => [
                                    'form'  => $form
                                ]
                            ])
                        ->close()
                    ->close()
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(TemplateBlock::class, [
                                'template'  => '@NetBSFichier/block/famille_link.block.twig',
                                'params'    => [
                                    'famille' => $famille,
                                ]
                            ])
                        ->close()
                    ->close()
                ->close()
                ->pushColumn(9)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(TabsCardBlock::class, ['tabs' => [
                                new Tab('Contact', '@NetBSFichier/block/tabs/sendable_contact.tab.twig', [
                                    'item'      => $famille,
                                    'form'      => $form
                                ]),
                                new Tab($famille, '@NetBSFichier/block/tabs/editable_contact.tab.twig', [
                                    'item'      => $famille,
                                    'form'      => $form
                                ]),
                                new Tab('Membres', '@NetBSFichier/famille/famille_membres.block.twig', [
                                    'famille'   => $famille
                                ]),
                                new Tab('Responsables légaux', "@NetBSFichier/famille/famille_geniteurs.block.twig", [
                                    'famille'   => $famille,
                                    'form'      => $form
                                ])
                            ]])
                        ->close()
                    ->close()
                ->close()
            ->close();

        return $layout->renderResponse('netbs', $config, [
            'title' => $famille->__toString(),
            'item'  => $famille
        ]);
    }


    /**
     * @Route("/remove/{id}", name="netbs.fichier.famille.remove")
     */
    public function removeFamilleAction($id, EventDispatcherInterface $dispatcher) {

        if(!$this->isGranted('ROLE_SG'))
            throw $this->createAccessDeniedException("Opération refusée!");

        $config = $this->get(FichierConfig::class);
        $em = $this->getDoctrine()->getManager();
        /** @var BaseFamille $famille */
        $famille = $em->find($config->getFamilleClass(), $id);

        foreach($famille->getMembres() as $membre) {
            $dispatcher->dispatch(new RemoveMembreEvent($membre, $em), RemoveMembreEvent::NAME);
            $em->remove($membre);
        }

        $dispatcher->dispatch(new RemoveFamilleEvent($famille, $em), RemoveFamilleEvent::NAME);

        $em->remove($famille);
        $em->flush();
        $this->addFlash('success', 'Famille supprimée');
        return $this->redirectToRoute('netbs.core.home.dashboard');
    }
}
