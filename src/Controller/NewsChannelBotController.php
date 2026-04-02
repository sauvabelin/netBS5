<?php

namespace App\Controller;

use App\Entity\NewsChannelBot;
use App\Form\NewsChannelBotType;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Annotation\Route;

class NewsChannelBotController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/news-channel-bot/manage', name: 'sauvabelin.news_channel_bot.manage')]
    #[IsGranted('ROLE_IT')]
    public function manageBotsAction() {
        return $this->render('newsChannelBot/manage_bots.html.twig');
    }


    #[Route('/modal/news-channel-bot/add', name: 'sauvabelin.news_channel_bot.add_modal')]
    #[IsGranted('ROLE_IT')]
    public function addNewsChannelModalAction(Request $request, EntityManagerInterface $em) {

        $bot = new NewsChannelBot();
        $form   = $this->createForm(NewsChannelBotType::class, $bot);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Nouveau bot créé");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'title' => "Nouveau bot",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

}


