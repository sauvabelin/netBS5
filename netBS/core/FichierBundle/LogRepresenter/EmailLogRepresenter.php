<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\FichierBundle\Mapping\BaseEmail;

class EmailLogRepresenter extends ContactRepresenter
{
    /**
     * @param BaseEmail $item
     * @return string
     */
    public function representBasic($item)
    {
        return "[email #" . $item->getId() . "] " . $item->getEmail();
    }

    /**
     * @param BaseEmail $item
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
            'nameLabel'     => 'Email',
            'action'        => $action,
            'oldValue'      => $oldValue,
            'newValue'      => $newValue,
            'basic'         => ['email', 'expediable', 'remarques'],
            'ownerLabel'    => 'Lié à',
            'ownerValue'    => $owner,
            'property'      => $property
        ]);
    }

    public function getRepresentedClass()
    {
        return $this->config->getEmailClass();
    }
}