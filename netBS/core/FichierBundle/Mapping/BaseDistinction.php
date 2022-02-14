<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;


/**
 * Distinction
 * @ORM\MappedSuperclass()
 */
abstract class BaseDistinction
{
    use RemarqueTrait;

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
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    public function __construct($nom = null)
    {
        $this->nom  = $nom;
    }

    public function __toString()
    {
        return $this->nom;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return BaseDistinction
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }
}

