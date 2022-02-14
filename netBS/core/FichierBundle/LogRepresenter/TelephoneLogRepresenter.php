<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseTelephone;

class TelephoneLogRepresenter extends ContactRepresenter
{
    /**
     * @param BaseTelephone $item
     * @return string
     */
    public function representBasic($item)
    {
        return "[téléphone #" . $item->getId() . "]" . $item->getTelephone();
    }

    /**
     * @param BaseTelephone $item
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
            'nameLabel'     => 'Téléphone',
            'oldValue'      => $oldValue,
            'action'        => $action,
            'newValue'      => $newValue,
            'basic'         => ['telephone', 'expediable', 'remarques'],
            'ownerLabel'    => 'Lié à',
            'ownerValue'    => $owner,
            'property'      => $property
        ]);
    }

    public function getRepresentedClass()
    {
        return $this->config->getTelephoneClass();
    }
}