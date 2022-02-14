<?php

namespace NetBS\FichierBundle\MassUpdater;

use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\FichierBundle\Form\AttributionType;
use NetBS\FichierBundle\Service\FichierConfig;

class AttributionMassUpdater extends BaseMassUpdater
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function getTitle() {

        return "Modifier les attributions sélectionnées";
    }

    /**
     * Returns this updater name
     * @return string
     */
    public function getName()
    {
        return 'netbs.mass_updater.attribution';
    }

    /**
     * Returns the updated item class
     * @return string
     */
    public function getUpdatedItemClass()
    {
        return $this->config->getAttributionClass();
    }

    public function getItemForm()
    {
        return AttributionType::class;
    }
}