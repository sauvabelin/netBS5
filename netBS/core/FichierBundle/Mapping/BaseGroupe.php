<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Entity\GroupeType;
use NetBS\FichierBundle\Model\ValidableInterface;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use NetBS\FichierBundle\Utils\Entity\ValidityTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Groupe
 *
 * @ORM\Table(name="fichier_groupes")
 * @ORM\Entity()
 */
abstract class BaseGroupe implements ValidableInterface
{
    use ValidityTrait, RemarqueTrait;

    const   OUVERT  = 'ouvert';
    const   FERME   = 'ferme';

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

    /**
     * @var BaseGroupe
     */
    protected $parent;

    /**
     * @var BaseGroupe[]
     */
    protected $enfants;

    /**
     * @var GroupeType
     * @Assert\NotBlank()
     */
    protected $groupeType;

    /**
     * @var BaseAttribution[]
     */
    protected $attributions;

    public function __construct()
    {
        $this->validity     = self::OUVERT;
        $this->enfants      = new ArrayCollection();
        $this->attributions = new ArrayCollection();
    }

    public static function getValidityChoices() {

        return [
            self::OUVERT    => 'Ouvert',
            self::FERME     => 'Fermé'
        ];
    }

    public function __toString()
    {
        return ucfirst($this->nom);
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
     * @return BaseGroupe
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
     * Set parent
     *
     * @param BaseGroupe $parent
     * @return self
     */
    public function setParent(BaseGroupe $parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get parent
     *
     * @return BaseGroupe $parent
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Add enfant
     *
     * @param BaseGroupe $enfant
     * @return $this
     */
    public function addEnfant(BaseGroupe $enfant)
    {
        $this->enfants[] = $enfant;
        $enfant->setParent($this);

        return $this;
    }

    /**
     * Remove enfant
     *
     * @param BaseGroupe $enfant
     */
    public function removeEnfant(BaseGroupe $enfant)
    {
        $this->enfants->removeElement($enfant);
        $enfant->setParent(null);
    }

    /**
     * Get enfants
     *
     * @return BaseGroupe[]
     */
    public function getEnfants()
    {
        return $this->enfants;
    }

    /**
     * Get enfants
     * @return BaseGroupe[]
     */
    public function getEnfantsRecursive() {

        $enfants    = $this->getEnfants()->toArray();

        foreach($this->getEnfants() as $g)
            $enfants = array_merge($enfants, $g->getEnfantsRecursive());

        return $enfants;
    }

    /**
     * @param BaseGroupeType $type
     * @return self
     */
    public function setGroupeType(BaseGroupeType $type)
    {
        $this->groupeType = $type;
        return $this;
    }

    /**
     * Get groupeModel
     *
     * @return BaseGroupeType
     */
    public function getGroupeType()
    {
        return $this->groupeType;
    }

    /**
     * @param BaseAttribution $attribution
     * @return $this
     */
    public function addAttribution(BaseAttribution $attribution) {

        $this->attributions[] = $attribution;
        $attribution->setGroupe($this);
        return $this;
    }

    /**
     * @param BaseAttribution $attribution
     * @return $this
     */
    public function removeAttribution(BaseAttribution $attribution) {

        $this->attributions->removeElement($attribution);
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getAttributions()
    {
        return $this->attributions;
    }

    /**
     * @return BaseAttribution[]
     */
    public function getActivesAttributions() {

        $attrs  = [];

        foreach($this->getAttributions() as $attribution)
            if($attribution->isActive())
                $attrs[] = $attribution;

        return $attrs;
    }

    /**
     * @return BaseAttribution[]
     */
    public function getActivesRecursivesAttributions() {

        $attrs      = $this->getActivesAttributions();

        foreach($this->getEnfantsRecursive() as $groupe)
            $attrs = array_merge($attrs, $groupe->getActivesAttributions());

        return $attrs;
    }

    /**
     * @return BaseAttribution[]
     */
    public function getRecursivesAttributions() {

        $attrs = $this->getAttributions()->toArray();
        foreach($this->getEnfantsRecursive() as $groupe)
            $attrs = array_merge($attrs, $groupe->getAttributions()->toArray());

        return $attrs;
    }
}

