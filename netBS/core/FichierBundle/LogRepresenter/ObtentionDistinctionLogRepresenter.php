<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseObtentionDistinction;

class ObtentionDistinctionLogRepresenter extends FichierRepresenter
{
    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param BaseObtentionDistinction $item
     * @return string
     */
    public function representBasic($item)
    {
        return '[Distinction attribuée #'. $item->getId() .'] - ' . $item->getDistinction();
    }

    /**
     * This will be called by the logger when the user checking on changes asks for more
     * details regarding this object
     * @param BaseObtentionDistinction $item
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
            'basic'         => ['distinction', 'membre', 'remarques'],
            'datic'         => ['date'],
            'nameLabel'     => 'Distinction attribuée',
        ]);
    }

    /**
     * The represented object class
     * @return string
     */
    public function getRepresentedClass()
    {
        return $this->config->getObtentionDistinctionClass();
    }
}