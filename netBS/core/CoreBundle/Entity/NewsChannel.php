<?php

namespace NetBS\CoreBundle\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

/**
 * NewsChannel
 *
 * @ORM\Table(name="netbs_core_news_channel")
 * @ORM\Entity(repositoryClass="NetBS\CoreBundle\Repository\NewsChannelRepository")
 */
class NewsChannel
{
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
     * @ORM\Column(name="nom", type="string", length=255, unique=true)
     */
    protected $nom;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="color", type="string", length=255, unique=true)
     */
    protected $color;

    /**
     * @var string
     *
     * @ORM\Column(name="postRule", type="text", nullable=true)
     */
    protected $postRule;

    /**
     * @var string
     *
     * @ORM\Column(name="read_rule", type="text", nullable=true)
     */
    protected $readRule;

    /**
     * @var News
     *
     * @ORM\OneToMany(targetEntity="News", mappedBy="channel")
     */
    protected $news;

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
     * Set nom.
     *
     * @param string $nom
     *
     * @return NewsChannel
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set postRule.
     *
     * @param string $postRule
     *
     * @return NewsChannel
     */
    public function setPostRule($postRule)
    {
        $this->postRule = $postRule;

        return $this;
    }

    /**
     * Get postRule.
     *
     * @return string
     */
    public function getPostRule()
    {
        return $this->postRule;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->news = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Add news.
     *
     * @param News $news
     *
     * @return NewsChannel
     */
    public function addNews(News $news)
    {
        $this->news[] = $news;

        return $this;
    }

    /**
     * Remove news.
     *
     * @param News $news
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeNews(News $news)
    {
        return $this->news->removeElement($news);
    }

    /**
     * Get news.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getNews()
    {
        $news = $this->news->toArray();
        usort($news, function(News $a, News $b) {
            if($a->isPinned() && !$b->isPinned())
                return 1;
            if($b->isPinned() && !$a->isPinned())
                return -1;

            return $a->getCreatedAt() <  $b->getCreatedAt();
        });
        return $news;
    }

    /**
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * @param string $color
     * @return NewsChannel
     */
    public function setColor($color)
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return string
     */
    public function getReadRule()
    {
        return $this->readRule;
    }

    /**
     * @param string $readRule
     * @return NewsChannel
     */
    public function setReadRule($readRule)
    {
        $this->readRule = $readRule;
        return $this;
    }
}
