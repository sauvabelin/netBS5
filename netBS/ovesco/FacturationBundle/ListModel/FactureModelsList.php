<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FactureModelsList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('OvescoFacturationBundle:FactureModel')->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return FactureModel::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.facture_models';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'name',
            ])
            ->addColumn('Signataire', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'signataire',
            ])
            ->addColumn('Nom du groupe', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'groupName',
            ])
            ->addColumn('Rue', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'rue',
            ])
            ->addColumn('Npa & Ville', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'npaVille',
            ])
            ->addColumn('Origine', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'cityFrom',
            ])
            ->addColumn('Poids', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY => 'poids',
            ])
            ->addColumn('Supprimer', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class,
                    ModalAction::class => [
                        ModalAction::ROUTE => function(FactureModel $model) { return $this->router->generate('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()]); }
                    ]
                ]
            ])
            ;
    }
}
