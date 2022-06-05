<?php

namespace App\ListModel;

use App\Entity\Cabane;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\ListModel\Action\IconAction;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\SimpleColumn;
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
            ->addColumn("Nom", 'nom', SimpleColumn::class)
            ->addColumn("ID Calendrier Google", 'calendarId', SimpleColumn::class)
            ->addColumn("Localisation", 'location', SimpleColumn::class)
            ->addColumn("Disponible à la location", 'enabled', SimpleColumn::class)
            ->addColumn('Actions', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    new ActionItem(IconAction::class,  [
                        IconAction::ICON    => "fas fa-edit",
                        LinkAction::TITLE   => "Editer",
                        LinkAction::ROUTE   => fn(Cabane $cabane) => $this->router->generate('sauvabelin.cabanes.edit', array('id' => $cabane->getId())),
                    ]),
                ]
            ])
        ;
    }
}