<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseAdresse;

class AdresseLogRepresenter extends ContactRepresenter
{
    /**
     * @param BaseAdresse $item
     * @return string
     */
    public function representBasic($item)
    {
        return "[adresse #" . $item->getId() . "]";
    }

    /**
     * @param BaseAdresse $item
     * @param string $action
     * @param $property
     * @param $oldValue
     * @param $newValue
     * @return string
     */
    public function representDetails($item, $action, $property, $oldValue, $newValue)
    {
        $owner  = $this->manager->findOwner($item);

        return $this->twig->render('@NetBSFichier/logging/contact.representation.twig', [
            'item'          => $item,
            'nameLabel'     => 'Adresse',
            'oldValue'      => $oldValue,
            'action'        => $action,
            'newValue'      => $newValue,
            'basic'         => ['rue', 'npa', 'localite', 'expediable', 'remarques'],
            'ownerLabel'    => 'Liée à',
            'ownerValue'    => $owner,
            'property'      => $property
        ]);
    }

    public function getRepresentedClass()
    {
        return $this->config->getAdresseClass();
    }
}