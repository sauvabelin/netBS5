<?php

namespace NetBS\FichierBundle\Model\Search;

use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;

class SearchAttribution
{
    /**
     * @var BaseGroupe
     */
    protected $groupe;

    /**
     * @var BaseFonction
     */
    protected $fonction;

    /**
     * @var \DateTime
     */
    protected $dateDebut;

    /**
     * @var \DateTime
     */
    protected $dateFin;

    /**
     * @var bool
     */
    protected $actif;

    /**
     * @return BaseGroupe
     */
    public function getGroupe()
    {
        return $this->groupe;
    }

    /**
     * @param BaseGroupe $groupe
     */
    public function setGroupe($groupe)
    {
        $this->groupe = $groupe;
    }

    /**
     * @return BaseFonction
     */
    public function getFonction()
    {
        return $this->fonction;
    }

    /**
     * @param BaseFonction $fonction
     */
    public function setFonction($fonction)
    {
        $this->fonction = $fonction;
    }

    /**
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * @param \DateTime $dateDebut
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;
    }

    /**
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * @param \DateTime $dateFin
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;
    }

    /**
     * @return bool
     */
    public function isActif()
    {
        return $this->actif;
    }

    /**
     * @param bool $actif
     */
    public function setActif($actif)
    {
        $this->actif = $actif;
    }
}