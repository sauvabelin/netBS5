<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Model\EqualInterface;
use NetBS\FichierBundle\Model\OwnableAdresse;
use NetBS\FichierBundle\Model\OwnableEmail;
use NetBS\FichierBundle\Model\OwnableTelephone;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Geniteur
 * @ORM\MappedSuperclass()
 */
abstract class BaseGeniteur extends Personne implements EqualInterface
{
    const       MERE                = 'mere';
    const       PERE                = 'pere';
    const       GRAND_PARENT        = 'grand_parent';
    const       REPRESENTANT_LEGAL  = 'representant_legal';
    const       AUTRE               = 'autre';

    /**
     * @var string
     * @ORM\Column(name="nom", type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    protected $nom;

    /**
     * @var string
     * @Groups({"details"})
     * @ORM\Column(name="profession", type="string", length=255, nullable=true)
     */
    protected $profession;

    /**
     * @var string
     * @Assert\NotBlank()
     * @Groups({"details"})
     * @ORM\Column(name="statut", type="string", length=255)
     */
    protected $statut;

    /**
     * @var BaseFamille
     * @Assert\NotBlank()
     * @Groups({"geniteurFamille"})
     */
    protected $famille;


    public function __toString()
    {
        return $this->prenom . ' ' . $this->getVisualNom();
    }

    public function equals($object)
    {
        return $object instanceof BaseGeniteur && $object->getId() === $this->getId();
    }

    public static function getStatutChoices() {

        return [

            self::MERE                  => "Mère",
            self::PERE                  => 'Père',
            self::REPRESENTANT_LEGAL    => 'Représentant légal',
            self::GRAND_PARENT          => 'Grand-parent',
            self::AUTRE                 => 'Autre'
        ];
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return BaseGeniteur
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
     * @return string
     */
    public function getVisualNom() {

        return empty($this->nom)
            ? $this->getFamille()->getNom()
            : $this->nom;
    }

    /**
     * Set profession
     *
     * @param string $profession
     *
     * @return BaseGeniteur
     */
    public function setProfession($profession)
    {
        $this->profession = $profession;

        return $this;
    }

    /**
     * Get profession
     *
     * @return string
     */
    public function getProfession()
    {
        return $this->profession;
    }

    /**
     * Set statut
     *
     * @param string $statut
     *
     * @return BaseGeniteur
     */
    public function setStatut($statut)
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get statut
     *
     * @return string
     */
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * Set famille
     *
     * @param BaseFamille $famille
     * @return self
     */
    public function setFamille(BaseFamille $famille)
    {
        $this->famille = $famille;
        return $this;
    }

    /**
     * Get famille
     *
     * @return BaseFamille $famille
     */
    public function getFamille()
    {
        return $this->famille;
    }

    /**
     * @return OwnableAdresse|null
     */
    public function getSendableAdresse() {

        foreach($this->getAdresses() as $adresse)
            if($adresse->getExpediable())
                return new OwnableAdresse($this, $adresse);

        if($this->famille)
            foreach($this->famille->getAdresses() as $adresse)
                if($adresse->getExpediable())
                    return new OwnableAdresse($this->famille, $adresse);

        if($this->famille)
            foreach($this->famille->getMembres() as $membre)
                foreach($membre->getAdresses() as $adresse)
                    if($adresse->getExpediable())
                        return new OwnableAdresse($membre, $adresse);

        foreach($this->getAdresses() as $adresse)
            return new OwnableAdresse($this, $adresse);

        if($this->famille)
            foreach($this->famille->getAdresses() as $adresse)
                return new OwnableAdresse($this->famille, $adresse);

        if($this->famille)
            foreach($this->famille->getMembres() as $membre)
                foreach($membre->getAdresses() as $adresse)
                    return new OwnableAdresse($membre, $adresse);
    }

    public function getSendableEmail()
    {
        foreach($this->getEmails() as $email)
            if($email->getExpediable())
                return new OwnableEmail($this, $email);

        if($this->famille)
            foreach($this->famille->getEmails() as $email)
                if($email->getExpediable())
                    return new OwnableEmail($this->famille, $email);
    }

    public function getSendableTelephone()
    {
        foreach($this->getTelephones() as $telephone)
            if($telephone->getExpediable())
                return new OwnableTelephone($this, $telephone);

        if($this->famille)
            foreach($this->famille->getTelephones() as $telephone)
                if($telephone->getExpediable())
                    return new OwnableTelephone($this->famille, $telephone);

    }
}

