<?php

namespace App\Model;

use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\ContactInformation;
use NetBS\FichierBundle\Entity\Email;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\Groupe;
use NetBS\FichierBundle\Entity\Telephone;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\GroupSequenceProviderInterface;
use App\Entity\TDGLFamille;
use App\Entity\TDGLMembre;

class Inscription implements GroupSequenceProviderInterface
{
    /**
     * @var int
     */
    public $familleId;

    /**
     * @var TDGLFamille
     */
    public $famille;

    /**
     * @Assert\NotBlank(groups={"default"})
     */
    public $prenom;

    /**
     * @Assert\NotBlank(groups={"default"})
     */
    public $sexe;

    /**
     * @var string
     * @Assert\NotBlank(groups={"default"})
     */
    public $nom;

    /**
     * @var string
     */
    public $numeroAvs;

    /**
     * @var \DateTime
     * @Assert\NotBlank(groups={"default"})
     * @Assert\Type("\DateTimeInterface")
     */
    public $naissance;

    /**
     * @var \DateTime
     */
    public $inscription;

    /**
     * @Assert\NotBlank(groups={"noFamily"})
     */
    public $adresse;

    /**
     * @Assert\NotBlank(groups={"noFamily"})
     */
    public $npa;

    /**
     * @Assert\NotBlank(groups={"noFamily"})
     */
    public $localite;

    /**
     * @Assert\NotBlank(groups={"noFamily"})
     */
    public $telephone;

    /**
     * @Assert\NotBlank(groups={"noFamily"})
     */
    public $email;

    /**
     * @var string
     */
    public $professionsParents;

    /**
     * @Assert\NotNull(groups={"default"})
     * @var Groupe
     */
    public $unite;

    /**
     * @Assert\NotNull(groups={"default"})
     * @var Fonction
     */
    public $fonction;

    public function __construct()
    {
        $this->inscription = new \DateTime();
    }

    public function generateFamille() {

        if ($this->famille)
            return $this->famille;

        $adresse = new Adresse();
        $adresse->setRue($this->adresse)
            ->setNpa($this->npa)
            ->setLocalite($this->localite)
            ->setPays('CH');

        $famille = new TDGLFamille();
        $famille->setValidity(BaseFamille::VALIDE);
        $famille->setContactInformation(new ContactInformation());
        $famille->setNom($this->nom);
        $famille->addAdresse($adresse);
        $famille->addTelephone(new Telephone($this->telephone));
        $famille->addEmail(new Email($this->email));
        $famille->setProfessionsParents($this->professionsParents);
        $this->famille = $famille;
        return $famille;
    }

    public function generateMembre() {
        $membre = new TDGLMembre();
        $membre->setContactInformation(new ContactInformation());
        $membre->setStatut(BaseMembre::INSCRIT)
            ->setNumeroAvs($this->numeroAvs)
            ->setInscription($this->inscription)
            ->setNaissance($this->naissance)
            ->setPrenom($this->prenom)
            ->setSexe($this->sexe);

        $attribution = new Attribution();
        $attribution->setFonction($this->fonction)
            ->setGroupe($this->unite);
        $membre->addAttribution($attribution);
        return $membre;
    }

    /**
     * Returns which validation groups should be used for a certain state
     * of the object.
     *
     * @return string[]|GroupSequence An array of validation groups
     */
    public function getGroupSequence()
    {
        return [
            'default',
            $this->famille ? '' : 'noFamily',
        ];
    }
}
