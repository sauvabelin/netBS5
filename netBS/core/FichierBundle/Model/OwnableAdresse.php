<?php

namespace NetBS\FichierBundle\Model;

use Doctrine\Common\Util\ClassUtils;
use NetBS\FichierBundle\Mapping\BaseAdresse;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\Personne;

class OwnableAdresse extends BaseAdresse
{
    /**
     * @var Personne|BaseFamille
     */
    protected $owner;

    /**
     * @var string
     */
    protected $type;

    /**
     * OwnableAdresse constructor.
     * @param Personne|BaseFamille $owner
     * @param BaseAdresse $adresse
     */
    public function __construct($owner, BaseAdresse $adresse)
    {
        $classdata      = explode('\\', ClassUtils::getRealClass(get_class($owner)));
        $this->owner    = $owner;
        $this->type     = strtolower($classdata[count($classdata) - 1]);

        $this->setExpediable($adresse->getExpediable())
            ->setLocalite($adresse->getLocalite())
            ->setNpa($adresse->getNpa())
            ->setRue($adresse->getRue())
            ->setPays($adresse->getPays())
            ->setRemarques($adresse->getRemarques());

        $this->id = $adresse->getId();
    }

    /**
     * @return Personne|BaseFamille
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
