<?php

namespace App\MessageHandler;

use App\Entity\BSUser;
use App\Entity\NewsChannelBot;
use App\Message\NewsNotification;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\News;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\SecureBundle\Service\SecureConfig;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Security\ExpressionLanguage;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NewsNotificationHandler
{
    private $em;

    private $el;

    private $config;

    private $log;

    public function __construct(EntityManagerInterface $em, SecureConfig $config, AdapterInterface $cache, LoggerInterface $log)
    {
        $this->em = $em;
        $this->config = $config;
        $this->log = $log;
        $this->el = new ExpressionLanguage($cache);
    }

    public function __invoke(NewsNotification $message)
    {
        /** @var News $news */
        $news = $this->em->find('NetBSCoreBundle:News', $message->getNewsId());
        if (!$news) {
            $this->log->warning("News not found from notification handler", [
                'id' => $message->getNewsId(),
            ]);
        }

        $users = $this->em->getRepository($this->config->getUserClass());
        $bots = $this->em->getRepository(NewsChannelBot::class)->findAll();
        foreach ($bots as $bot) {
            if (in_array($news->getChannel(), $bot->getChannels())) {
                $this->dispatch($news, $bot, $users);
            }
        }
    }

    private function dispatch(News $news, NewsChannelBot $bot, $users) {
        $rule = $news->getChannel()->getReadRule();

        foreach ($users as $user) {
            if (empty($rule) || $this->el->evaluate($rule, ['user' => $user])) {
                $this->send($news, $bot, $user);
            }
        }
    }

    private function send(News $news, NewsChannelBot $bot, BSUser $user) {
        
    }

    private function getTalkConversation(NewsChannelBot $bot, BSUser $user) {


    }

    private function apiCall(NewsChannelBot $bot, string $path, array $params) {

        $url = "";
    }
}