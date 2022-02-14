<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Mailer\MailChannel;
use NetBS\CoreBundle\Model\MailerConfig;
use NetBS\SecureBundle\Mapping\BaseUser;
use App\Entity\BSUser;

class Mailer
{
    /**
     * @var MailChannel[]
     */
    private $channels = [];

    private $config;

    private $twig;

    private $mailer;

    public function __construct(MailerConfig $config, \Twig_Environment $twig, \Swift_Mailer $mailer)
    {
        $this->config   = $config;
        $this->twig     = $twig;
        $this->mailer   = $mailer;
    }

    public function registerChannel(MailChannel $channel) {

        $this->channels[] = $channel;
    }

    public function sendInChannel($channel, BSUser $to, $params = [], $subject = null, $from = null) {

        $channel    = $this->channels[$channel];
        $message    = new \Swift_Message();
        $message
            ->setSubject($channel->getSubject($subject))
            ->setFrom($channel->getFrom($from))
            ->setTo($to->getSendableEmail())
            ->setBody($this->buildContent($channel->getHtmlTemplate(), $params), 'text/html');

        $this->mailer->send($message);
    }

    public function send($template, $subject, $to, $params = [], $from = null) {

        $fromMail   = $from === null ? $this->config->getDefaultFrom() : $from;
        $toMail     = $to instanceof BaseUser ? $to->getSendableEmail() : $to;
        $message    = new \Swift_Message();
        $message
            ->setSubject($this->config->getSubjectPrefix() . $subject)
            ->setFrom($fromMail)
            ->setTo($toMail)
            ->setBody($this->buildContent($template, $params), 'text/html');

        $this->mailer->send($message);
    }

    private function buildContent($template, $params = []) {

        return $this->twig->render($template, $params);
    }
}
