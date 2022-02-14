<?php

namespace NetBS\FichierBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class DoctrinePostLoadContactSubscriber implements EventSubscriber
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist
        ];
    }

    public function prePersist(LifecycleEventArgs $events) {

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