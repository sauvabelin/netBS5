<?php

namespace App\ListModel;

use App\Entity\Cabane;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CabaneList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('App:Cabane')->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Cabane::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "sauvabelin.apmbs.cabanes";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "nom",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("ID Calendrier Google", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "calendarId",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Localisation", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "location",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Disponible à la location", null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => SwitchType::class,
                XEditableColumn::PROPERTY   => 'enabled',
            ])
        ;
    }
}