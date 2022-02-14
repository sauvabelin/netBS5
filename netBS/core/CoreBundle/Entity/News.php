<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Validator\Constraints as Assert;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * News
 *
 * @ORM\Table(name="netbs_core_news")
 * @ORM\Entity(repositoryClass="NetBS\CoreBundle\Repository\NewsRepository")
 */
class News
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
     * @Assert\NotBlank()
     * @ORM\Column(name="titre", type="string", length=255)
     */
    protected $titre;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="contenu", type="text")
     */
    protected $contenu;

    /**
     * @var BaseUser
     */
    protected $user;

    /**
     * @var bool
     *
     * @ORM\Column(name="pinned", type="boolean")
     */
    protected $pinned;

    /**
     * @var NewsChannel
     * @Assert\NotBlank()
     * @ORM\ManyToOne(targetEntity="NewsChannel", inversedBy="news")
     */
    protected $channel;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set titre.
     *
     * @param string $titre
     *
     * @return News
     */
    public function setTitre($titre)
    {
        $this->titre = $titre;

        return $this;
    }

    /**
     * Get titre.
     *
     * @return string
     */
    public function getTitre()
    {
        return $this->titre;
    }

    /**
     * Set contenu.
     *
     * @param string $contenu
     *
     * @return News
     */
    public function setContenu($contenu)
    {
        $this->contenu = $contenu;

        return $this;
    }

    /**
     * Get contenu.
     *
     * @return string
     */
    public function getContenu()
    {
        return $this->contenu;
    }

    /**
     * Set user.
     *
     * @param $user
     *
     * @return News
     */
    public function setUser(BaseUser $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return BaseUser
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set channel.
     *
     * @param NewsChannel $channel
     *
     * @return News
     */
    public function setChannel(NewsChannel $channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Get channel.
     *
     * @return NewsChannel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return bool
     */
    public function isPinned()
    {
        return $this->pinned;
    }

    /**
     * @param bool $pinned
     * @return News
     */
    public function setPinned($pinned)
    {
        $this->pinned = $pinned;

        return $this;
    }
}
