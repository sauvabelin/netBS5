<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use NetBS\CoreBundle\Validator\Constraints as BSAssert;

/**
 * Attribution
 * @ORM\MappedSuperclass()
 * @BSAssert\User(rule="user.hasRole('ROLE_SG')")
 */
abstract class BaseAttribution
{
    use RemarqueTrait, TimestampableEntity;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"default"})
     */
    protected $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dateDebut", type="datetime")
     * @Groups({"default"})
     * @Assert\NotBlank()
     */
    protected $dateDebut;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dateFin", type="datetime", nullable=true)
     * @Groups({"default"})
     */
    protected $dateFin;

    /**
     * @var BaseGroupe
     * @Assert\NotBlank()
     * @Groups({"attributionWithGroupe"})
     */
    protected $groupe;

    /**
     * @var BaseFonction
     * @Assert\NotBlank()
     * @Groups({"attributionWithFonction"})
     */
    protected $fonction;

    /**
     * @var BaseMembre
     * @Assert\NotBlank()
     * @Groups({"attributionWithMembre"})
     */
    protected $membre;


    /**
     * Méthode de tris d'attributions
     * @var \Closure
     */
    public static $sortFuncion = null;

    public function __construct()
    {
        $this->dateDebut    = new \DateTime();

        if(self::$sortFuncion === null) {
            self::$sortFuncion = function(BaseAttribution $a, BaseAttribution $b) {

                if($a->getFonction()->getPoids() == $b->getFonction()->getPoids())
                    return $a->getMembre()->getInscription() < $b->getMembre()->getInscription() ? -1 : 1;

                return $a->getFonction()->getPoids() > $b->getFonction()->getPoids() ? -1 : 1;

            };
        }
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

    public function __toString()
    {
        return $this->getFonction()->getNom() . ' - ' . $this->getGroupe()->getNom();
    }

    /**
     * Set dateDebut
     *
     * @param \DateTime $dateDebut
     *
     * @return BaseAttribution
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut
     *
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set dateFin
     *
     * @param \DateTime $dateFin
     *
     * @return BaseAttribution
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin
     *
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set groupe
     *
     * @param BaseGroupe $groupe
     * @return self
     */
    public function setGroupe(BaseGroupe $groupe)
    {
        $this->groupe = $groupe;
        return $this;
    }

    /**
     * Get groupe
     *
     * @return BaseGroupe $groupe
     */
    public function getGroupe()
    {
        return $this->groupe;
    }

    /**
     * Set fonction
     *
     * @param BaseFonction $fonction
     * @return self
     */
    public function setFonction(BaseFonction $fonction)
    {
        $this->fonction = $fonction;
        return $this;
    }

    /**
     * Get fonction
     *
     * @return BaseFonction $fonction
     */
    public function getFonction()
    {
        return $this->fonction;
    }

    /**
     * @return BaseMembre
     */
    public function getMembre() {

        return $this->membre;
    }

    /**
     * @param BaseMembre $membre
     * @return $this
     */
    public function setMembre(BaseMembre $membre) {

        $this->membre = $membre;
        return $this;
    }

    /**
     * @return bool
     */
    public function isActive() {

        $now    = new \DateTime();
        return $this->dateDebut < $now && ($this->dateFin === null || $this->dateFin > $now);
    }

    public static function getSortFunction() {

        return function(BaseAttribution $a, BaseAttribution $b) {

            if($a->getFonction()->getPoids() == $b->getFonction()->getPoids())
                return $a->getDateDebut() < $b->getDateDebut() ? -1 : 1;

            return $a->getFonction()->getPoids() > $b->getFonction()->getPoids() ? -1 : 1;

        };
    }

    //Serializer helpers

    /**
     * @return int
     * @Groups({"default"})
     */
    public function getFonctionId() {

        return $this->fonction->getId();
    }

    /**
     * @return int
     * @Groups({"default"})
     */
    public function getGroupeId() {

        return $this->groupe->getId();
    }

    /**
     * @return int
     * @Groups({"default"})
     */
    public function getMembreId() {

        return $this->membre->getId();
    }

    /**
     * @return string
     * @Groups({"default"})
     */
    public function getRepresentation() {

        return $this->__toString();
    }
}

