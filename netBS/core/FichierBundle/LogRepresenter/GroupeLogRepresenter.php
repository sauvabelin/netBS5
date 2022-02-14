<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseGroupe;

class GroupeLogRepresenter extends FichierRepresenter
{

    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param BaseGroupe $item
     * @return string
     */
    public function representBasic($item)
    {
        return "[Groupe #" . $item->getId() . "] " . $item->getNom();
    }

    /**
     * This will be called by the logger when the user checking on changes asks for more
     * details regarding this object
     * @param BaseGroupe $item
     * @param string $action 'create','update', 'delete'
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
            'basic'         => ['nom', 'parent', 'groupeType', 'validity', 'remarques'],
            'nameLabel'     => 'Groupe',
        ]);
    }

    /**
     * The represented object class
     * @return string
     */
    public function getRepresentedClass()
    {
        return $this->config->getGroupeClass();
    }
}