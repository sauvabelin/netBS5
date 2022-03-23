<?php

namespace NetBS\FichierBundle\MassUpdater;

use NetBS\CoreBundle\Model\BaseMassUpdater;
use NetBS\FichierBundle\Form\AttributionType;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AttributionMassUpdater extends BaseMassUpdater
{
    protected $config;

    protected $dispatcher;

    public function __construct(FichierConfig $config, EventDispatcherInterface $dispatcher)
    {
        $this->config   = $config;
        $this->dispatcher = $dispatcher;
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