<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseGeniteur;

class GeniteurLogRepresenter extends FichierRepresenter
{
    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param BaseGeniteur $item
     * @return string
     */
    public function representBasic($item)
    {
        return '[Géniteur #'. $item->getId() .'] - ' . $item->__toString();
    }

    /**
     * This will be called by the logger when the user checking on changes asks for more
     * details regarding this object
     * @param BaseGeniteur $item
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
            'basic'         => ['nom', 'prenom', 'sexe', 'profession', 'statut', 'remarques'],
            'nameLabel'     => 'Géniteur',
        ]);
    }

    /**
     * The represented object class
     * @return string
     */
    public function getRepresentedClass()
    {
        return $this->config->getGeniteurClass();
    }
}