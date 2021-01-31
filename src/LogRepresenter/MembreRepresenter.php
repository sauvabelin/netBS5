<?php

namespace App\LogRepresenter;

use NetBS\FichierBundle\LogRepresenter\MembreLogRepresenter;
use NetBS\FichierBundle\Mapping\BaseMembre;
use App\Entity\BSMembre;

class MembreRepresenter extends MembreLogRepresenter
{
    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param BSMembre $item
     * @return string
     */
    public function representBasic($item)
    {
        return '[Membre #'. $item->getNumeroBS() .'] - ' . $item->getFullName();
    }

    /**
     * This will be called by the logger when the user checking on changes asks for more
     * details regarding this object
     * @param BaseMembre $item
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
            'basic'         => ['famille', 'sexe', 'numeroBS', 'statut', 'remarques'],
            'datic'         => ['naissance', 'inscription', 'desinscription'],
            'nameLabel'     => 'Membre',
        ]);
    }
}
