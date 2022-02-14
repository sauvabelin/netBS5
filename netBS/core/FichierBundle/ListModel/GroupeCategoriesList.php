<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class GroupeCategoriesList extends BaseListModel
{
    use EntityManagerTrait, FichierConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository($this->getManagedItemsClass())->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getGroupeCategorieClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.groupe_categories';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY   => 'nom'
            ))
            ->addColumn('Remarques', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => TextareaType::class,
                XEditableColumn::PROPERTY   => 'remarques'
            ))
        ;
    }
}