<?php

namespace NetBS\CoreBundle\Mailer;

use NetBS\CoreBundle\Model\MailerConfig;

class MailChannel
{
    /**
     * @var MailerConfig
     */
    private $config;

    /**
     * @var string
     */
    private $alias;

    /**
     * @var string
     */
    private $from = null;

    /**
     * @var string
     */
    private $subject = null;

    /**
     * @var string
     */
    private $htmlTemplate = null;

    public function __construct(MailerConfig $config, $alias, $from, $subject, $htmlTemplate)
    {
        $this->config       = $config;
        $this->alias        = $alias;
        $this->from         = $from;
        $this->subject      = $subject;
        $this->htmlTemplate = $htmlTemplate;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param $other
     * @return string
     */
    public function getFrom($other)
    {
        if(empty($other))
            return empty($this->from) ? $this->config->getDefaultFrom() : $this->from;

        return $other;
    }

    /**
     * @param $other
     * @return string
     */
    public function getSubject($other)
    {
        if($other === null)
            return $this->config->getSubjectPrefix() . $this->subject;

        return $this->config->getSubjectPrefix() . $other;
    }

    /**
     * @return string
     */
    public function getHtmlTemplate()
    {
        return $this->htmlTemplate;
    }
}