<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\AbstractDynamicListModel;
use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\ConfiguratorTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class DynamicFactureList extends AbstractDynamicListModel
{
    use RouterTrait, ConfiguratorTrait, FichierConfigTrait;

    public function getManagedName()
    {
        return "Factures";
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Facture::class;
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('numero', 'factureId', SimpleColumn::class)
            ->addColumn('Débiteur', 'debiteur', HelperColumn::class)
            ->addColumn('statut', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => ChoiceType::class,
                XEditableColumn::PROPERTY => 'statut',
                XEditableColumn::PARAMS => ['choices' => Facture::getStatutChoices()]
            ])
            ->addColumn("Dernière impression", 'latestImpression', DateTimeColumn::class)
            ->addColumn('Montant', 'montant', SimpleColumn::class)
            ->addColumn('Payé', 'montantPaye', SimpleColumn::class)
            ->addColumn('', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    new ActionItem(ModalAction::class, [
                        ModalAction::TEXT => '+ paiement',
                        ModalAction::ROUTE => function(Facture $facture) {
                            return $this->router->generate('ovesco.facturation.paiement.modal_add', ['id' => $facture->getId()]);
                        }
                    ]),
                    new ActionItem(ModalAction::class, [
                        ModalAction::ICON => 'fas fa-expand',
                        ModalAction::ROUTE => function(Facture $facture) {
                            return $this->router->generate('ovesco.facturation.facture_modal', ['id' => $facture->getId()]);
                        }
                    ])
                ]
            ])
        ;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.dynamics.factures';
    }
}
