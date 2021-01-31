<?php

namespace App\Entity;

use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Validator\Constraints as BSAssert;

/**
 * Class Membre
 * @package App\Entity
 * @ORM\Entity()
 * @ORM\Table(name="sauvabelin_netbs_membres")
 * @BSAssert\User(rules={
 *     "numeroBS":"user.hasRole('ROLE_SG')"
 * })
 */
class BSMembre extends BaseMembre
{
    /**
     * @var integer
     * @ORM\Column(name="numero_bs", type="integer", length=30, nullable=true)
     */
    protected $numeroBS;

    /**
     * @var int
     */
    private $_adabsId;

    /**
     * We need adabs ID for facturation shit^2
     * @param $adabsId
     */
    public function _setAdabsId($adabsId) {
        $this->_adabsId = intval($adabsId);
    }

    /**
     * @return int
     */
    public function getNumeroBS()
    {
        return $this->numeroBS;
    }

    /**
     * @param int $numeroBS
     * @return $this
     */
    public function setNumeroBS($numeroBS)
    {
        $this->numeroBS = $numeroBS;
        return $this;
    }

    public function setDesinscription($desinscription)
    {
        if ($desinscription <= new \DateTime()) $this->setStatut(self::DESINSCRIT);
        return parent::setDesinscription($desinscription);
    }

    public function setStatut($statut)
    {
        $this->statut = $statut;
        $close = [];
        if($statut === self::DECEDE) {
            foreach ($this->getActivesAttributions() as $attribution)
                $close[] = $attribution;

            if ($this->desinscription === null) $this->desinscription = new \DateTime();
        }
        else if(in_array($statut, [self::DESINSCRIT, self::AUTRE])) {
            foreach ($this->getActivesAttributions() as $attribution)
                if ($attribution->getGroupeId() !== $this->_adabsId)
                    $close[] = $attribution;

            if ($this->desinscription === null) $this->desinscription = new \DateTime();
        }

        /** @var BaseAttribution $attribution */
        foreach($close as $attribution)
            $attribution->setDateFin(new \DateTime());

        return $this;
    }

    public function consideredInscrit()
    {
        $adabs = false;
        foreach($this->getActivesAttributions() as $attribution)
            if ($attribution->getGroupeId() === $this->_adabsId)
                $adabs = true;

        return $this->statut === BaseMembre::INSCRIT && !$adabs;
    }
}
