<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Block\LayoutManager;
use NetBS\CoreBundle\Block\Model\Tab;
use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Block\TabsCardBlock;
use NetBS\CoreBundle\Block\TemplateBlock;
use NetBS\CoreBundle\Event\RemoveMembreEvent;
use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Service\DynamicListManager;
use NetBS\CoreBundle\Service\ExporterManager;
use NetBS\FichierBundle\Form\Personne\MembreType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MembreController
 * @Route("/membre")
 */
class MembreController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param int $id
     * @Route("/page/{id}", name="netbs.fichier.membre.page_membre")
     * @return Response
     */
    public function pageMembreAction($id, ExporterManager $exporterManager, EntityManagerInterface $em, LayoutManager $designer, DynamicListManager $dynamics) {

        $exporters  = $exporterManager->getExportersForClass($this->config->getMembreClass());

        /** @var BaseMembre $membre */
        $membre     = $em->find($this->config->getMembreClass(), $id);
        $form       = $this->createForm(MembreType::class, $membre)->createView();

        if(!$membre)
            throw $this->createNotFoundException();

        if(!$this->isGranted(CRUD::READ, $membre))
            throw $this->createAccessDeniedException("Accès à la page de {$membre->getFullName()} refusé");

        $lists = $dynamics->getAvailableLists($this->config->getMembreClass());
        $config = $designer::configurator()
            ->addRow()
                ->pushColumn(3)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(CardBlock::class, [
                                'template'  => "@NetBSFichier/membre/presentation.block.twig",
                                'title'     => $membre->__toString(),
                                'subtitle'  => ($attr = $membre->getActiveAttribution()) ? $attr : null,
                                'params'    => [
                                    'form'  => $form
                            ]])
                        ->close()
                    ->close()
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(TemplateBlock::class, [
                                'template'  => '@NetBSFichier/block/membre_links.block.twig',
                                'params'    => [
                                    'membre'        => $membre,
                                    'lists'         => $lists,
                                    'exporters'     => $exporters,
                                    'exportClass'   => base64_encode($this->config->getMembreClass()),
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
                                    'item'          => $membre,
                                    'idealOwner'    => $membre->getFamille()
                                ]),
                                new Tab($membre, '@NetBSFichier/block/tabs/editable_contact.tab.twig', [
                                    'item'  => $membre,
                                    'form'  => $form
                                ]),
                                new Tab('Attributions et distinctions', '@NetBSFichier/block/tabs/attributions_distinctions.tab.twig', [
                                    'membre'    => $membre
                                ])
                            ]])
                        ->close()
                    ->close()
                ->close()
            ->close();

        return $designer->renderResponse('netbs', $config, [
            'title' => $membre->__toString(),
            'item'  => $membre
        ]);
    }


    /**
     * @Route("/search", name="netbs.fichier.membre.search")
     * @return Response
     */
    public function searchMembreAction(SearcherManager $searcher) {
        $instance = $searcher->bind($this->config->getMembreClass());
        return $searcher->render($instance);
    }

    /**
     * @Route("/remove/{id}", name="netbs.fichier.membre.remove")
     */
    public function removeMembreAction($id, EventDispatcherInterface $dispatcher) {

        if(!$this->isGranted('ROLE_SG'))
            throw $this->createAccessDeniedException("Opération refusée!");

        $em = $this->getDoctrine()->getManager();
        $membre = $em->find($this->config->getMembreClass(), $id);

        $dispatcher->dispatch(new RemoveMembreEvent($membre, $em), RemoveMembreEvent::NAME);

        $em->remove($membre);
        $em->flush();
        $this->addFlash('success', 'Membre supprimé');
        return $this->redirectToRoute('netbs.core.home.dashboard');
    }
}
