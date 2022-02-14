<?php

namespace Ovesco\FacturationBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;

class DoctrineDebiteurSubscriber implements EventSubscriber
{
    const MEMBRE    = 'membre';
    const FAMILLE   = 'famille';
    const GENITEUR  = 'geniteur';

    private $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function getSubscribedEvents()
    {
        return [
            'postLoad'
        ];
    }

    public function postLoad(LifecycleEventArgs $args) {

        $item       = $args->getEntity();

        if(!$item instanceof Facture && !$item instanceof Creance)
            return;

        $data       = explode(':', $item->_getDebiteurId());
        $class      = $this->config->getGeniteurClass();
        if ($data[0] === self::MEMBRE) $class = $this->config->getMembreClass();
        else if ($data[0] === self::FAMILLE) $class = $this->config->getFamilleClass();

        $debiteur   = $args->getEntityManager()->find($class, $data[1]);
        if ($debiteur === null) {
            throw new \Exception("Debiteur introuvable");
        }
        $item->setDebiteur($debiteur);
    }

    /**
     * @param BaseMembre|BaseFamille $debiteur
     *
     * @return string
     */
    public static function createId($debiteur) {

        $str = self::GENITEUR;
        if ($debiteur instanceof BaseFamille) $str = self::FAMILLE;
        else if ($debiteur instanceof BaseMembre) $str = self::MEMBRE;

        return $str . ":" . $debiteur->getId();

    }
}
