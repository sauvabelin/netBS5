<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FamilleMembresList extends BaseListModel
{
    const FAMILLE_ID = 'familleId';

    use EntityManagerTrait, FichierConfigTrait, MembreListHelper;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::FAMILLE_ID);
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository($this->getFichierConfig()->getFamilleClass())
            ->find($this->getParameter(self::FAMILLE_ID))->getMembres();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.famille.membres';
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getMembreClass();
    }
}