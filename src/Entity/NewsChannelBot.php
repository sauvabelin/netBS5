<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * @ORM\Table(name="news_channel_bot")
 * @ORM\Entity()
 */
class NewsChannelBot
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
     * @ORM\ManyToMany(targetEntity="NetBS\CoreBundle\Entity\NewsChannel")
     */
    protected $channels;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    protected $name;

    /**
     * @ORM\Column(name="description", type="string", length=255)
     */
    protected $description;

    /**
     * @ORM\Column(name="nc_username", type="string", length=255)
     */
    protected $ncUsername;

    /**
     * @ORM\Column(name="nc_password", type="string", length=255)
     */
    protected $ncPassword;

    public function __construct()
    {
        $this->channels = new ArrayCollection();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    public function addChannel($channel) {
        $this->channels->add($channel);
    }

    public function removeChannel($channel) {
        $this->channels->removeElement($channel);
    }

    public function getChannels() {
        return $this->channels->toArray();
    }

    public function setNcUsername($username)
    {
        $this->ncUsername = $username;
        return $this;
    }

    public function getNcUsername() {
        return $this->ncUsername;
    }

    public function setNcPassword($password)
    {
        $this->ncPassword = $password;
        return $this;
    }

    public function getNcPassword() {
        return $this->ncPassword;
    }
}
