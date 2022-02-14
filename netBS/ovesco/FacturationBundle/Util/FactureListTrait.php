<?php

namespace Ovesco\FacturationBundle\Util;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\ListModel\Column\FactureCreancesColumn;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

trait FactureListTrait
{
    use RouterTrait;

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Facture::class;
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('numero', null, HelperColumn::class)
            ->addColumn('Débiteur', 'debiteur', HelperColumn::class)
            ->addColumn('statut', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => ChoiceType::class,
                XEditableColumn::PROPERTY => 'statut',
                XEditableColumn::PARAMS => ['choices' => Facture::getStatutChoices()]
            ])
            ->addColumn('Création', 'date', DateTimeColumn::class)
            ->addColumn("Dernière impression", 'latestImpression', DateTimeColumn::class)
            ->addColumn('Montant', function(Facture $facture) { return $facture->getMontant() . ".-"; }, SimpleColumn::class)
            ->addColumn('Payé', function(Facture $facture) { return $facture->getMontantPaye() . ".-"; }, SimpleColumn::class)
            ->addColumn('Rappels', function(Facture $facture) {
                return count($facture->getRappels());
            }, SimpleColumn::class)
            ->addColumn('Creances', 'creances', FactureCreancesColumn::class)
            ->addColumn('Remarques', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'remarques',
            ])
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
                    ]),
                    new ActionItem(ModalAction::class, [
                        ModalAction::ICON => 'fas fa-file-alt',
                        ModalAction::ROUTE => function(Facture $facture) {
                            return $this->router->generate('ovesco.facturation.pdf_facture_modal', ['id' => $facture->getId()]);
                        }
                    ]),
                    new ActionItem(RemoveAction::class)
                ]
            ])
        ;
    }
}
