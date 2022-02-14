<?php

namespace Ovesco\FacturationBundle\MassUpdater;

use NetBS\CoreBundle\Model\BaseMassUpdater;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Form\CreanceType;

class CreanceMassUpdater extends BaseMassUpdater
{
    /**
     * Returns this updater name
     * @return string
     */
    public function getName()
    {
        return 'hochet.facturation.creance';
    }

    public function getTitle()
    {
        return "Modifier les créances sélectionnées";
    }

    /**
     * Returns the updated item class
     * @return string
     */
    public function getUpdatedItemClass()
    {
        return Creance::class;
    }

    public function getItemForm()
    {
        return CreanceType::class;
    }
}