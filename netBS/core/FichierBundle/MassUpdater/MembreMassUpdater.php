<?php

namespace NetBS\FichierBundle\MassUpdater;

use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\FichierBundle\Form\MembreUpdaterType;
use NetBS\FichierBundle\Service\FichierConfig;

class MembreMassUpdater extends BaseMassUpdater
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function getTitle() {

        return "Modifier les membres sélectionnés";
    }

    /**
     * Returns this updater name
     * @return string
     */
    public function getName()
    {
        return 'netbs.mass_updater.membre';
    }

    /**
     * @return bool
     */
    public function showToString()
    {
        return true;
    }

    /**
     * Returns the updated item class
     * @return string
     */
    public function getUpdatedItemClass()
    {
        return $this->config->getMembreClass();
    }

    public function getItemForm()
    {
        return MembreUpdaterType::class;
    }
}