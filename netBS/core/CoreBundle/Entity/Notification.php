<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * @ORM\Table(name="netbs_core_notifications")
 * @ORM\Entity()
 */
class Notification
{
    use TimestampableEntity;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(name="message", type="text")
     */
    protected $message;

    /**
     * @var string
     * @ORM\Column(name="route", type="string", length=255)
     */
    protected $route;

    /**
     * @var BaseUser
     */
    protected $user;

    public function __construct(BaseUser $user, $message, $route = null)
    {
        $this->user     = $user;
        $this->message  = $message;
        $this->route    = $route;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }

    /**
     * @return BaseUser
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param BaseUser $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
}

