<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\News;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\CoreBundle\Form\NewsChannelType;
use NetBS\CoreBundle\Form\NewsType;
use NetBS\CoreBundle\Utils\Modal;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class NewsController
 * @package App\Controller
 * @Route("/news")
 */
class NewsController extends AbstractController
{
    /**
     * @Route("/manage", name="netbs.core.news.manage")
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_SG')")
     */
    public function manageNewsAction() {
        return $this->render("@NetBSCore/news/manage_news.html.twig");
    }

    /**
     * @return Response
     * @Route("/read", name="netbs.core.news.read_news")
     */
    public function readNewsAction(EntityManagerInterface $em, TokenStorageInterface $tokenStorage) {

        $user = $tokenStorage->getToken()->getUser();
        $channels = $em->getRepository(NewsChannel::class)->findReadableChannels($user);
        return $this->render('@NetBSCore/news/read_news.html.twig', [
            'channels' => $channels
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @route("/modal/add-channel/{id}", defaults={"id"=null}, name="netbs.core.news.modal_add_channel")
     * @Security("is_granted('ROLE_SG')")
     */
    public function addNewsChannelModalAction(Request $request, $id, EntityManagerInterface $em) {

        $title      = $id ? "Modifier" : "Créer";
        $channel    = $id ? $em->find('NetBSCoreBundle:NewsChannel', $id) : new NewsChannel();

        $form   = $this->createForm(NewsChannelType::class, $channel);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Opération sur channel réussie");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'title' => $title . " une channel",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @Route("/modal-add-edit-news/{id}", defaults={"id"=null}, name="netbs.core.news.modal_edit_news")
     */
    public function modalAddEditNewsAction(Request $request, $id, EntityManagerInterface $em) {

        $title  = $id ? "Modifier" : "Publier";
        $news   = new News();

        if($id)
            $news = $em->find('NetBSCoreBundle:News', $id);
        else
            $news->setUser($this->getUser());

        $form   = $this->createForm(NewsType::class, $news);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            /** @var News $news */
            $news       = $form->getData();

            $em->persist($news);
            $em->flush();

            $this->addFlash("success", "News publiée!");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'title' => $title . ' une news',
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
