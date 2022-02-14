<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use NetBS\SecureBundle\Mapping\BaseRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fonction
 * @ORM\MappedSuperclass()
 */
abstract class BaseFonction
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
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="abbreviation", type="string", length=255)
     */
    protected $abbreviation;

    /**
     * @var int
     * @Assert\Type("integer")
     * @ORM\Column(name="poids", type="integer", nullable=true)
     */
    protected $poids;

    /**
     * @var BaseRole[]
     */
    protected $roles;

    public function __construct()
    {
        $this->roles    = new ArrayCollection();
        $this->poids    = 20;
    }

    public function __toString()
    {
        return ucfirst($this->getNom());
    }

    public function equalsTo(BaseFonction $fonction) {

        return $this->getNom() == $fonction->getNom();
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
     * @return BaseFonction
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
     * Set abbreviation
     *
     * @param string $abbreviation
     *
     * @return BaseFonction
     */
    public function setAbbreviation($abbreviation)
    {
        $this->abbreviation = $abbreviation;

        return $this;
    }

    /**
     * Get abbreviation
     *
     * @return string
     */
    public function getAbbreviation()
    {
        return $this->abbreviation;
    }

    /**
     * Set poids
     *
     * @param integer $poids
     *
     * @return BaseFonction
     */
    public function setPoids($poids)
    {
        $this->poids = intval($poids);

        return $this;
    }

    /**
     * Get poids
     *
     * @return int
     */
    public function getPoids()
    {
        return $this->poids;
    }

    /**
     * @param BaseRole $role
     * @return $this
     */
    public function addRole(BaseRole $role) {

        $this->roles->add($role);
        return $this;
    }

    /**
     * @param BaseRole $role
     * @return $this
     */
    public function removeRole(BaseRole $role) {

        $this->roles->removeElement($role);
        return $this;
    }

    /**
     * Get roles
     *
     * @return BaseRole[]
     */
    public function getRoles()
    {
        return $this->roles->toArray();
    }
}

