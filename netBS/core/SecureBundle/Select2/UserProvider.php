<?php

namespace NetBS\SecureBundle\Select2;

use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\SecureBundle\Mapping\BaseUser;

class UserProvider implements Select2ProviderInterface
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param BaseUser $item
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }

    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass()
    {
        return $this->getSecureConfig()->getUserClass();
    }

    /**
     * Returns string representation of the given managed object
     * @param BaseUser $item
     * @return string
     */
    public function toString($item)
    {
        return $item->getUsername();
    }

    /**
     * Search for objects related to the given needle
     * @param $needle
     * @param int $limit
     * @return array
     */
    public function search($needle, $limit = 5)
    {
        return $this->entityManager->getRepository($this->getManagedClass())->findByUsername($needle);
    }
}