<?php

namespace NetBS\FichierBundle\Subscriber;

use Doctrine\ORM\Event\PrePersistEventArgs;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class DoctrinePostLoadContactSubscriber
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    public function prePersist(PrePersistEventArgs $events): void {

        if(!in_array(get_class($events->getObject()), [
            $this->config->getGeniteurClass(),
            $this->config->getMembreClass(),
            $this->config->getFamilleClass()
        ])) return;

        /** @var BaseMembre|BaseFamille|BaseGeniteur $item */
        $item   = $events->getObject();
        $class  = $this->config->getContactInformationClass();

        if($item->getContactInformation() === null)
            $item->setContactInformation(new $class());
    }
}