<?php

namespace NetBS\FichierBundle\Model\Search;

use NetBS\CoreBundle\Model\Daterange;

class SearchBaseMembreInformation
{
    /**
     * @var string
     */
    protected $prenom;

    /**
     * @var string
     */
    protected $nom;

    /**
     * @var Daterange
     */
    protected $naissance;

    /**
     * @var string
     */
    protected $sexe;

    /**
     * @var string
     */
    protected $statut;

    /**
     * @var Daterange
     */
    protected $inscription;
    /**
     * @var Daterange
     */
    protected $desinscription;

    /**
     * @var SearchAttribution
     */
    protected $attributions;

    /**
     * @var SearchObtentionDistinction
     */
    protected $obtentionsDistinction;

    /**
     * @return string
     */
    public function getPrenom()
    {
        return $this->prenom;
    }

    /**
     * @param string $prenom
     */
    public function setPrenom($prenom)
    {
        $this->prenom = $prenom;
    }

    /**
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     */
    public function setNom($nom)
    {
        $this->nom = $nom;
    }

    /**
     * @return Daterange
     */
    public function getNaissance()
    {
        return $this->naissance;
    }

    /**
     * @param Daterange $naissance
     */
    public function setNaissance($naissance)
    {
        $this->naissance = $naissance;
    }

    /**
     * @return string
     */
    public function getSexe()
    {
        return $this->sexe;
    }

    /**
     * @param string $sexe
     */
    public function setSexe($sexe)
    {
        $this->sexe = $sexe;
    }

    /**
     * @return string
     */
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * @param string $statut
     */
    public function setStatut($statut)
    {
        $this->statut = $statut;
    }

    /**
     * @return Daterange
     */
    public function getInscription()
    {
        return $this->inscription;
    }

    /**
     * @param Daterange $inscription
     */
    public function setInscription($inscription)
    {
        $this->inscription = $inscription;
    }

    /**
     * @return Daterange
     */
    public function getDesinscription()
    {
        return $this->desinscription;
    }

    /**
     * @param Daterange $desinscription
     */
    public function setDesinscription($desinscription)
    {
        $this->desinscription = $desinscription;
    }

    /**
     * @return SearchAttribution
     */
    public function getAttributions()
    {
        return $this->attributions;
    }

    /**
     * @param SearchAttribution $attributions
     */
    public function setAttributions($attributions)
    {
        $this->attributions = $attributions;
    }

    /**
     * @return SearchObtentionDistinction
     */
    public function getObtentionsDistinction()
    {
        return $this->obtentionsDistinction;
    }

    /**
     * @param SearchObtentionDistinction $obtentionsDistinction
     */
    public function setObtentionsDistinction($obtentionsDistinction)
    {
        $this->obtentionsDistinction = $obtentionsDistinction;
    }
}