<?php

namespace NetBS\CoreBundle\Model;

class MailerConfig
{
    /**
     * @var string
     */
    private $subjectPrefix;

    /**
     * @var string
     */
    private $defaultFrom;

    public function __construct($prefix, $from)
    {
        $this->subjectPrefix    = $prefix;
        $this->defaultFrom      = $from;
    }

    /**
     * @return string
     */
    public function getSubjectPrefix()
    {
        return $this->subjectPrefix;
    }

    /**
     * @return string
     */
    public function getDefaultFrom()
    {
        return $this->defaultFrom;
    }
}