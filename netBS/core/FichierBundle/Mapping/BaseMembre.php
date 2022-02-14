<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Model\EqualInterface;
use NetBS\FichierBundle\Model\OwnableAdresse;
use NetBS\FichierBundle\Model\OwnableEmail;
use NetBS\FichierBundle\Model\OwnableTelephone;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use NetBS\CoreBundle\Validator\Constraints as BSAssert;

/**
 * Membre
 * @ORM\MappedSuperclass()
 * @BSAssert\User(rule="user.hasRole('ROLE_SG')")
 */
abstract class BaseMembre extends Personne implements EqualInterface
{
    const   INSCRIT     = 'inscrit';
    const   DESINSCRIT  = 'desinscrit';
    const   PAUSE       = 'pause';
    const   DECEDE      = 'decede';
    const   AUTRE       = 'autre';

    /**
     * @var BaseFamille
     * @Groups({"withFamille"})
     * @Assert\NotBlank()
     */
    protected $famille;

    /**
     * @var BaseAttribution[]
     * @Groups({"withAttributions", "details"})
     */
    protected $attributions;

    /**
     * @var BaseObtentionDistinction[]
     * @Groups({"withDistinctions", "details"})
     */
    protected $obtentionsDistinction;

    /**
     * @var \DateTime
     * @Assert\Type("\DateTimeInterface")
     * @Assert\NotBlank
     * @Groups({"default"})
     * @ORM\Column(name="naissance", type="datetime")
     */
    protected $naissance;

    /**
     * @var \DateTime
     * @Assert\Type("\DateTimeInterface")
     * @Assert\NotBlank
     * @ORM\Column(name="inscription", type="datetime")
     * @Groups({"default"})
     */
    protected $inscription;

    /**
     * @var \DateTime
     * @Assert\Type("\DateTimeInterface")
     * @ORM\Column(name="desinscription", type="datetime", nullable=true)
     * @Groups({"default"})
     */
    protected $desinscription;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="statut", type="string", length=255)
     * @Groups({"default"})
     */
    protected $statut;

    /**
     * @var string
     * @ORM\Column(name="num_avs", type="string", length=255, nullable=true)
     * @Assert\Regex("/^(\d{3}).?(\d{4}).?(\d{4}).?(\d{2})$/", message="Numéro AVS au format 123.1234.1234.12")
     */
    protected $numeroAvs;

    //Quick data
    /**
     * Défini automatiquement lorsque la famille est mise à jour
     * @var string
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    // Store

    public function __construct()
    {
        $this->naissance                = new \DateTime();
        $this->inscription              = new \DateTime();
        $this->attributions             = new ArrayCollection();
        $this->obtentionsDistinction    = new ArrayCollection();
    }

    public static function getStatutChoices() {

        return [
            self::INSCRIT     => 'inscrit',
            self::DESINSCRIT  => 'désinscrit',
            self::PAUSE       => 'pause',
            self::DECEDE      => 'décédé',
            self::AUTRE       => 'autre',
        ];
    }

    public function equals($object)
    {
        return $object instanceof BaseMembre && $object->getId() === $this->getId();
    }

    public function hasFonction($fonction) {

        foreach($this->getActivesAttributions() as $attribution) {

            if(is_string($fonction))
                if($attribution->getFonction()->getNom() === $fonction)
                    return true;

                elseif($fonction instanceof BaseFonction)
                    if($fonction->equalsTo($attribution->getFonction()))
                        return true;
        }

        return false;
    }

    public function hasDistinction($distinction) {

        foreach($this->getObtentionsDistinction() as $od) {

            if(is_string($distinction))
                if($od->getDistinction()->getNom() === $distinction)
                    return true;

                elseif($distinction instanceof BaseDistinction)
                    if($distinction->getId() === $od->getDistinction()->getId())
                        return true;
        }

        return false;
    }

    public function isInGroup($groupe) {

        foreach($this->getActivesAttributions() as $attribution) {

            if(is_string($groupe))
                if($attribution->getGroupe()->getNom() === $groupe)
                    return true;

            elseif($groupe instanceof BaseGroupe)
                if($groupe->getId() === $attribution->getGroupe()->getId())
                    return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullName();
    }

    public function _setNom() {

        $this->nom  = $this->famille->getNom();
    }

    public function _getNom() {

        return !empty($this->nom) ? $this->nom : $this->famille->getNom();
    }

    /**
     * Used for elastica mapping
     * @return string
     * @Groups({"default"})
     */
    public function getFullName() {

        return $this->prenom . " " . $this->nom;
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
        $this->_setNom();
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
     * Add attribution
     *
     * @param BaseAttribution $attribution
     * @return $this
     */
    public function addAttribution(BaseAttribution $attribution)
    {
        $this->attributions[] = $attribution;
        $attribution->setMembre($this);
        return $this;
    }

    /**
     * @param \Traversable $attributions
     * @return $this
     */
    public function setAttributions(\Traversable $attributions) {

        $this->attributions = $attributions;
        return $this;
    }

    /**
     * Get attributions
     *
     * @return BaseAttribution[] $attributions
     */
    public function getAttributions()
    {
        return $this->attributions;
    }

    /**
     * Retournes la première attribution active trouvée
     * @return BaseAttribution|null
     * @Groups({"default"})
     */
    public function getActiveAttribution() {

        $attributions   = $this->getActivesAttributions();
        usort($attributions, function(BaseAttribution $a1, BaseAttribution $a2) {
            return $a1->getFonction()->getPoids() < $a2->getFonction()->getPoids() ? 1 : -1;
        });

        return count($attributions) > 0 ? $attributions[0] : null;
    }

    /**
     * Retournes les attributions actives
     * @return BaseAttribution[]|BaseAttribution
     */
    public function getActivesAttributions() {

        $return = [];

        /** @var BaseAttribution $attribution */
        foreach($this->attributions as $attribution)
            if($attribution->isActive())
                $return[] = $attribution;

        return $return;
    }

    /**
     * Add obtentionsDistinction
     *
     * @param BaseObtentionDistinction $obtentionsDistinction
     * @return $this
     */
    public function addObtentionDistinction(BaseObtentionDistinction $obtentionsDistinction)
    {
        $this->obtentionsDistinction[] = $obtentionsDistinction;
        $obtentionsDistinction->setMembre($this);
        return $this;
    }

    /**
     * Get obtentionsDistinction
     *
     * @return BaseObtentionDistinction[] $obtentionsDistinction
     */
    public function getObtentionsDistinction()
    {
        return $this->obtentionsDistinction;
    }

    /**
     * Set naissance
     *
     * @param \DateTime $naissance
     *
     * @return BaseMembre
     */
    public function setNaissance($naissance)
    {
        $this->naissance = $naissance;

        return $this;
    }

    /**
     * Get naissance
     *
     * @return \DateTime
     */
    public function getNaissance()
    {
        return $this->naissance;
    }

    /**
     * @return int
     */
    public function getAge() {

        return $this->naissance->diff(new \DateTime())->y;
    }

    /**
     * Set inscription
     *
     * @param \DateTime $inscription
     *
     * @return BaseMembre
     */
    public function setInscription($inscription)
    {
        $this->inscription = $inscription;

        return $this;
    }

    /**
     * Get inscription
     *
     * @return \DateTime
     */
    public function getInscription()
    {
        return $this->inscription;
    }

    /**
     * Set desinscription
     *
     * @param \DateTime $desinscription
     *
     * @return BaseMembre
     */
    public function setDesinscription($desinscription)
    {
        $this->desinscription = $desinscription;

        return $this;
    }

    /**
     * Get desinscription
     *
     * @return \DateTime
     */
    public function getDesinscription()
    {
        return $this->desinscription;
    }

    public function consideredInscrit() {
        return $this->statut === self::INSCRIT;
    }

    /**
     * Set statut
     *
     * @param string $statut
     *
     * @return BaseMembre
     * @throws \Exception
     */
    public function setStatut($statut)
    {
        $this->statut = $statut;

        if(in_array($statut, [self::DECEDE, self::DESINSCRIT])) {
            foreach ($this->getActivesAttributions() as $attribution)
                $attribution->setDateFin(new \DateTime());
            if ($this->desinscription === null) $this->desinscription = new \DateTime();
        }

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
     * @return string
     */
    public function getNumeroAvs()
    {
        $num = $this->numeroAvs;
        if ($num) {
            return $num[0] .
                $num[1] .
                $num[2] . '.' .
                $num[3] .
                $num[4] .
                $num[5].
                $num[6] . '.' .
                $num[7] .
                $num[8] .
                $num[9] .
                $num[10] . '.' .
                $num[11] .
                $num[12];
        }
        return null;
    }

    /**
     * @param string $numeroAvs
     * @return BaseMembre
     */
    public function setNumeroAvs($numeroAvs)
    {
        $this->numeroAvs = str_replace('.', '', $numeroAvs);
        return $this;
    }

    /**
     * @Groups({"membreAdresse"})
     * @return OwnableAdresse|null
     */
    public function getSendableAdresse() {

        foreach($this->getAdresses() as $adresse)
            if($adresse->getExpediable())
                return new OwnableAdresse($this, $adresse);

        foreach($this->famille->getAdresses() as $adresse)
            if($adresse->getExpediable())
                return new OwnableAdresse($this->famille, $adresse);

        foreach($this->famille->getGeniteurs() as $geniteur)
            foreach($geniteur->getAdresses() as $adresse)
                if($adresse->getExpediable())
                    return new OwnableAdresse($geniteur, $adresse);

        foreach($this->getAdresses() as $adresse)
            return new OwnableAdresse($this, $adresse);

        foreach($this->famille->getAdresses() as $adresse)
            return new OwnableAdresse($this->famille, $adresse);

        foreach($this->famille->getGeniteurs() as $geniteur)
            foreach($geniteur->getAdresses() as $adresse)
                return new OwnableAdresse($geniteur, $adresse);
    }

    /**
     * @Groups({"membreTelephone"})
     * @return OwnableTelephone
     */
    public function getSendableTelephone() {

        foreach($this->getTelephones() as $telephone)
            if($telephone->getExpediable())
                return new OwnableTelephone($this, $telephone);

        foreach($this->getFamille()->getTelephones() as $telephone)
            if($telephone->getExpediable())
                return new OwnableTelephone($this->getFamille(), $telephone);

        foreach($this->getFamille()->getGeniteurs() as $geniteur)
            foreach($geniteur->getTelephones() as $telephone)
                if($telephone->getExpediable())
                    return new OwnableTelephone($geniteur, $telephone);

        foreach($this->getTelephones() as $telephone)
            return new OwnableTelephone($this, $telephone);

        foreach($this->getFamille()->getTelephones() as $telephone)
            return new OwnableTelephone($this->getFamille(), $telephone);

        foreach($this->getFamille()->getGeniteurs() as $geniteur)
            foreach($geniteur->getTelephones() as $telephone)
                return new OwnableTelephone($geniteur, $telephone);
    }

    /**
     * @Groups({"membreEmail"})
     * @return OwnableEmail
     */
    public function getSendableEmail() {

        foreach($this->getEmails() as $email)
            if($email->getExpediable())
                return new OwnableEmail($this, $email);

        foreach($this->getFamille()->getEmails() as $email)
            if($email->getExpediable())
                return new OwnableEmail($this->getFamille(), $email);

        foreach($this->getFamille()->getGeniteurs() as $geniteur)
            foreach($geniteur->getEmails() as $email)
                if($email->getExpediable())
                    return new OwnableEmail($geniteur, $email);

        foreach($this->getEmails() as $email)
            return new OwnableEmail($this, $email);

        foreach($this->getFamille()->getEmails() as $email)
            return new OwnableEmail($this->getFamille(), $email);

        foreach($this->getFamille()->getGeniteurs() as $geniteur)
            foreach($geniteur->getEmails() as $email)
                return new OwnableEmail($geniteur, $email);
    }
}


