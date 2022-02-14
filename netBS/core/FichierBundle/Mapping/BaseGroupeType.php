<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\MappedSuperclass()
 */
class BaseGroupeType
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
     * @Assert\NotBlank
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var bool
     *
     * @ORM\Column(name="affichageEffectifs", type="boolean")
     */
    protected $affichageEffectifs;

    /**
     * @var BaseGroupeCategorie
     * @Assert\NotBlank()
     */
    protected $groupeCategorie;

    public function __construct()
    {
        $this->affichageEffectifs   = false;
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
     * @return BaseGroupeType
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

    /**
     * Set affichageEffectifs
     *
     * @param boolean $affichageEffectifs
     *
     * @return BaseGroupeType
     */
    public function setAffichageEffectifs($affichageEffectifs)
    {
        $this->affichageEffectifs = $affichageEffectifs;

        return $this;
    }

    /**
     * Get affichageEffectifs
     *
     * @return bool
     */
    public function getAffichageEffectifs()
    {
        return $this->affichageEffectifs;
    }

    /**
     * Set groupeCategorie
     *
     * @param BaseGroupeCategorie $groupeCategorie
     * @return self
     */
    public function setGroupeCategorie(BaseGroupeCategorie $groupeCategorie)
    {
        $this->groupeCategorie = $groupeCategorie;
        return $this;
    }

    /**
     * Get groupeCategorie
     *
     * @return BaseGroupeCategorie $groupeCategorie
     */
    public function getGroupeCategorie()
    {
        return $this->groupeCategorie;
    }
}

