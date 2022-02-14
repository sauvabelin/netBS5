<?php

namespace NetBS\FichierBundle\MassUpdater;

use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\FichierBundle\Form\ObtentionDistinctionType;
use NetBS\FichierBundle\Service\FichierConfig;

class ObtentionDistinctionMassUpdater extends BaseMassUpdater
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function getTitle() {

        return "Modifier les distinctions sélectionnées";
    }

    /**
     * Returns this updater name
     * @return string
     */
    public function getName()
    {
        return 'netbs.mass_updater.obtention_distinction';
    }

    /**
     * Returns the updated item class
     * @return string
     */
    public function getUpdatedItemClass()
    {
        return $this->config->getObtentionDistinctionClass();
    }

    public function getItemForm()
    {
        return ObtentionDistinctionType::class;
    }
}