<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseAttribution;

class AttributionLogRepresenter extends FichierRepresenter
{

    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param BaseAttribution $item
     * @return string
     */
    public function representBasic($item)
    {
        return "[Attribution #". $item->getId() ."] (" . $item->getMembre()->getFullName() . ")";
    }

    /**
     * @param object $item
     * @param string $action
     * @param $property
     * @param $oldValue
     * @param $newValue
     * @return string
     */
    public function representDetails($item, $action, $property, $oldValue, $newValue)
    {
        return $this->twig->render('@NetBSFichier/logging/basic_logging.representation.twig', [
            'item'          => $item,
            'action'        => $action,
            'property'      => $property,
            'oldValue'      => $oldValue,
            'newValue'      => $newValue,
            'basic'         => ['membre', 'fonction', 'groupe', 'remarques'],
            'datic'         => ['dateDebut', 'dateFin'],
            'nameLabel'     => 'Attribution',
        ]);
    }

    /**
     * The represented object class
     * @return string
     */
    public function getRepresentedClass()
    {
        return $this->config->getAttributionClass();
    }
}