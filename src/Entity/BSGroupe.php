<?php

namespace App\Entity;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class BSGroupe
 * @package App\Entity
 * @ORM\Entity()
 * @ORM\Table(name="sauvabelin_netbs_groupes")
 * @ORM\HasLifecycleCallbacks
 */
class BSGroupe extends BaseGroupe
{
    /**
     * On crée une vue SQL pour les accès au groupe, groupName<->username
     * @ORM\Column(name="nc_group_name", type="string", length=255, nullable=true)
     */
    protected $ncGroupName = null;

    /**
     * @var boolean
     * @ORM\Column(name="nc_mapped", type="boolean", length=255)
     */
    protected $ncMapped = false;

    /**
     * @return string
     */
    public function getNcGroupName()
    {
        return $this->ncGroupName;
    }

    public function updateNCGroupName()
    {
        $this->ncGroupName = self::toNCGroupId($this);
    }

    public static function toNCGroupId(BaseGroupe $groupe) {
        $name = "[" . $groupe->getId() . "] " . $groupe->getNom();

        if($groupe->getGroupeType())
            $name .= " (" . $groupe->getGroupeType()->getNom() . ")";
        return $name;
    }

    /**
     * @return bool
     */
    public function isNcMapped()
    {
        return $this->ncMapped;
    }

    /**
     * @param bool $ncMapped
     */
    public function setNcMapped($ncMapped)
    {
        $this->ncMapped = $ncMapped;
    }

    /**
     * @ORM\PrePersist()
     */
    public function PrePersist(LifecycleEventArgs $args) {

        if($this->ncGroupName === null)
            $this->updateNCGroupName();
    }

    /**
     * @ORM\PreUpdate()
     */
    public function PreUpdate(PreUpdateEventArgs $args) {

        if($this->ncGroupName === null)
            $this->updateNCGroupName();
    }
}
