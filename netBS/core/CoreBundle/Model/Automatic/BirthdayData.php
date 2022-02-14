<?php

namespace NetBS\CoreBundle\Model\Automatic;

use Symfony\Component\Validator\Constraints as Assert;

class BirthdayData
{
    /**
     * @var \DateTime
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    private $from;

    /**
     * @var \DateTime
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    private $to;

    /**
     * @return \DateTime
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param \DateTime $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @return \DateTime
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param \DateTime $to
     */
    public function setTo($to)
    {
        $this->to = $to;
    }
}