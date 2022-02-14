<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\CoreBundle\Model\EqualInterface;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Model\EmailableInterface;
use NetBS\FichierBundle\Model\OwnableAdresse;
use NetBS\FichierBundle\Model\OwnableEmail;
use NetBS\FichierBundle\Model\OwnableTelephone;
use NetBS\FichierBundle\Model\TelephonableInterface;
use NetBS\FichierBundle\Model\ValidableInterface;
use NetBS\FichierBundle\Utils\Entity\ContactTrait;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use NetBS\FichierBundle\Utils\Entity\ValidityTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use NetBS\CoreBundle\Validator\Constraints as BSAssert;

/**
 * Famille
 * @ORM\MappedSuperclass
 * @BSAssert\User(rule="user.hasRole('ROLE_SG')")
 */
abstract class BaseFamille implements AdressableInterface, TelephonableInterface, EmailableInterface, ValidableInterface, EqualInterface
{
    const   VALIDE      = 'valide';
    const   INVALIDE    = 'invalide';
    const   EN_ATTENTE  = 'en_attente';

    use ContactTrait, RemarqueTrait, ValidityTrait, TimestampableEntity;

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
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="nom", type="string", length=255)
     * @Groups({"default"})
     */
    protected $nom;

    /**
     * @var BaseMembre[]
     *
     * @Groups({"familleMembres"})
     */
    protected $membres;

    /**
     * @var BaseGeniteur[]
     * @Assert\Valid
     * @Groups({"familleGeniteurs"})
     */
    protected $geniteurs;

    /**
     * @var BaseContactInformation
     * @Assert\Valid()
     */
    protected $contactInformation;

    public function __construct()
    {
        $this->validity             = self::EN_ATTENTE;
        $this->membres              = new ArrayCollection();
        $this->geniteurs            = new ArrayCollection();
    }

    public static function getValidityChoices() {

        return [
            self::VALIDE        => self::VALIDE,
            self::INVALIDE      => self::INVALIDE,
            self::EN_ATTENTE    => 'En attente de validation'
        ];
    }

    public function equals($object)
    {
        return $object instanceof BaseFamille && $object->getId() === $this->getId();
    }

    public function __toString()
    {
        return 'Famille ' . $this->getNom();
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
     * @return BaseFamille
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        foreach($this->membres as $membre)
            $membre->_setNom();

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
     * Add membre
     *
     * @param BaseMembre $membre
     */
    public function addMembre(BaseMembre $membre)
    {
        $this->membres[] = $membre;
        $membre->setFamille($this);
    }

    /**
     * Remove membre
     *
     * @param BaseMembre $membre
     */
    public function removeMembre(BaseMembre $membre)
    {
        $this->membres->removeElement($membre);
    }

    /**
     * Get membres
     *
     * @return BaseMembre[] $membres
     */
    public function getMembres()
    {
        return $this->membres;
    }

    /**
     * Add geniteur
     *
     * @param BaseGeniteur $geniteur
     */
    public function addGeniteur(BaseGeniteur $geniteur)
    {
        $this->geniteurs[] = $geniteur;
        $geniteur->setFamille($this);
    }

    /**
     * Remove geniteur
     *
     * @param BaseGeniteur $geniteur
     */
    public function removeGeniteur(BaseGeniteur $geniteur)
    {
        $this->geniteurs->removeElement($geniteur);
    }

    /**
     * Get geniteurs
     *
     * @return BaseGeniteur[] $geniteurs
     */
    public function getGeniteurs()
    {
        return $this->geniteurs;
    }

    /**
     * @return OwnableAdresse|null
     * @Groups({"familleAdresse"})
     */
    public function getSendableAdresse() {

        foreach($this->getAdresses() as $adresse)
            if($adresse->getExpediable())
                return new OwnableAdresse($this, $adresse);

        foreach($this->getGeniteurs() as $geniteur)
            foreach($geniteur->getAdresses() as $adresse)
            if($adresse->getExpediable())
                return new OwnableAdresse($geniteur, $adresse);

        foreach($this->getMembres() as $membre)
            foreach($membre->getAdresses() as $adresse)
                if($adresse->getExpediable())
                    return new OwnableAdresse($membre, $adresse);

        foreach($this->getAdresses() as $adresse)
            return new OwnableAdresse($this, $adresse);

        foreach($this->getGeniteurs() as $geniteur)
            foreach($geniteur->getAdresses() as $adresse)
                return new OwnableAdresse($geniteur, $adresse);

        foreach($this->getMembres() as $membre)
            foreach($membre->getAdresses() as $adresse)
                return new OwnableAdresse($membre, $adresse);
    }

    /**
     * @return OwnableTelephone
     * @Groups({"familleTelephone"})
     */
    public function getSendableTelephone()
    {
        foreach($this->getTelephones() as $telephone)
            if($telephone->getExpediable())
                return new OwnableTelephone($this, $telephone);

        foreach($this->geniteurs as $geniteur)
            foreach($geniteur->getTelephones() as $telephone)
                if($telephone->getExpediable())
                    return new OwnableTelephone($geniteur, $telephone);

        foreach($this->membres as $membre)
            foreach($membre->getTelephones() as $telephone)
                if($telephone->getExpediable())
                    return new OwnableTelephone($membre, $telephone);

        foreach($this->getTelephones() as $telephone)
            return new OwnableTelephone($this, $telephone);

        foreach($this->geniteurs as $geniteur)
            foreach($geniteur->getTelephones() as $telephone)
                return new OwnableTelephone($geniteur, $telephone);

        foreach($this->membres as $membre)
            foreach($membre->getTelephones() as $telephone)
                return new OwnableTelephone($membre, $telephone);
    }

    /**
     * @return OwnableEmail
     * @Groups({"familleEmail", "details"})
     */
    public function getSendableEmail()
    {
        foreach($this->getEmails() as $email)
            if($email->getExpediable())
                return new OwnableEmail($this, $email);

        foreach($this->geniteurs as $geniteur)
            foreach($geniteur->getEmails() as $email)
                if($email->getExpediable())
                    return new OwnableEmail($geniteur, $email);

        foreach($this->membres as $membre)
            foreach($membre->getEmails() as $email)
                if($email->getExpediable())
                    return new OwnableEmail($membre, $email);

        foreach($this->getEmails() as $email)
            return new OwnableEmail($this, $email);

        foreach($this->geniteurs as $geniteur)
            foreach($geniteur->getEmails() as $email)
                return new OwnableEmail($geniteur, $email);

        foreach($this->membres as $membre)
            foreach($membre->getEmails() as $email)
                return new OwnableEmail($membre, $email);

    }
}

