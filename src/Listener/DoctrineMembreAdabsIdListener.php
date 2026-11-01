<?php

namespace App\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingNotConfiguredException;
use App\Entity\BSMembre;
use Psr\Log\LoggerInterface;

class DoctrineMembreAdabsIdListener
{
    private $params;

    public function __construct(ParameterManager $params, private LoggerInterface $logger)
    {
        $this->params = $params;
    }

    public function postLoad(LifecycleEventArgs $args) {
        $entity = $args->getEntity();
        if (!$entity instanceof BSMembre)
            return;

        // Runs on every membre load: a missing setting must not break every page.
        try {
            $entity->_setAdabsId($this->params->getValue('bs', 'groupe.adabs_id'));
        } catch (SettingNotConfiguredException $e) {
            // Keep the stored id while its groupe is in the trash: BSMembre::setStatut() would
            // otherwise close the ADABS attribution of a member being unsubscribed.
            $entity->_setAdabsId($this->params->getStoredValue('bs', 'groupe.adabs_id'));
            $this->logger->warning($e->getMessage());
        }
    }
}
