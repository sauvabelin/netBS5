<?php

namespace App\MessageHandler;

use App\Entity\BSUser;
use App\Entity\NewsChannelBot;
use App\Message\NewsNotification;
use App\Model\NextcloudDiscussion;
use App\Service\NextcloudApiCall;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\News;
use NetBS\SecureBundle\Service\SecureConfig;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Security\ExpressionLanguage;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

const ROOM_ENDPOINT = "/ocs/v2.php/apps/spreed/api/v4/room";
const CHAT_ENDPOINT = "/ocs/v2.php/apps/spreed/api/v1";

#[AsMessageHandler]
class NewsNotificationHandler
{
    private $em;

    private $el;

    private $config;

    private $log;

    private $nc;

    private $cache;

    public function __construct(EntityManagerInterface $em, SecureConfig $config, AdapterInterface $cache, NextcloudApiCall $nc, LoggerInterface $log)
    {
        $this->em = $em;
        $this->config = $config;
        $this->log = $log;
        $this->nc = $nc;
        $this->cache = $cache;
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

        $users = $this->em->getRepository($this->config->getUserClass())->findAll();
        $bots = $this->em->getRepository('App:NewsChannelBot')->findAll();
        foreach ($bots as $bot) {
            if (in_array($news->getChannel(), $bot->getChannels())) {
                $this->dispatch($news, $bot, $users);
            }
        }
    }

    private function dispatch(News $news, NewsChannelBot $bot, $users) {
        $rule = $news->getChannel()->getReadRule();

        /** @var BSUser $user */
        foreach ($users as $user) {
            if (empty($rule) || $this->el->evaluate($rule, ['user' => $user])) {
                try {
                    $this->send($news, $bot, $user); // First try
                } catch (\Exception $e) {
                    $this->log->info("Initial attempt at sending message failed", [
                        'bot' => $bot->getName(),
                        'user' => $user->getUsername(),
                        'error' => $e->getMessage(),
                    ]);

                    // Retry and forcing the convo creation
                    $this->send($news, $bot, $user, false);
                }
            }
        }
    }

    private function send(News $news, NewsChannelBot $bot, BSUser $user, bool $fromCache = true) {

        dump('send' . $news->getContenu() . " to " . $user->getUsername() . " on " . $bot->getName());
        return;
        $token = $this->getConversationToken($bot, $user, $fromCache);

        // Send message
        $this->nc->runQuery('POST', CHAT_ENDPOINT . "/chat/" . $token, [
            'message' => $news->getContenu(),
            'actorDisplayName' => $bot->getName(),
        ]);
    }

    private function getConversationToken(NewsChannelBot $bot, BSUser $user, bool $fromCache = true) {

        // Try to get conversation token from cache
        $cacheKey = $user->getUsername() . "-" . $bot->getId();
        $cachedToken = $this->cache->getItem($cacheKey);

        if ($fromCache && !$cachedToken->isHit()) {
            $convo = $this->createConversation($bot, $user);
            $cachedToken->set($convo->getToken());
            $this->cache->save($cachedToken);
        }

        return $cachedToken->get();
    }

    private function createConversation(NewsChannelBot $bot, BSUser $user) {

        $conversationData = $this->nc->runQuery('POST', ROOM_ENDPOINT, [
            'roomType' => 1,
            'invite' => $user->getUsername(),
            'roomName' => $bot->getName(),
        ]);

        return new NextcloudDiscussion($conversationData);
    }
}