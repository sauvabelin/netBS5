<?php

namespace NetBS\FichierBundle\Loader;

use NetBS\CoreBundle\Model\LoaderInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;

class AdressableLoader implements LoaderInterface
{
    use EntityManagerTrait, FichierConfigTrait;

    public function getLoadableClass()
    {
        return AdressableInterface::class;
    }

    public function toId($item)
    {
        $prefix = $item instanceof BaseMembre ? 'm' : 'f';
        return $prefix . $item->getId();
    }

    public function fromId($id)
    {
        $type = $id[0];
        $key = substr($id, 1, strlen($id));
        $class = $type === 'f'
            ? $this->fichierConfig->getFamilleClass()
            : $this->fichierConfig->getMembreClass();
        return $this->entityManager->find($class, $key);
    }
}
