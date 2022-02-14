<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Model\TogglableRow;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DebiteurFacturesList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('OvescoFacturationBundle:Facture')
            ->findBy(['debiteurId' => $this->getParameter('debiteurId')]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('debiteurId');
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Facture::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.debiteur_factures';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('numero', 'factureId', SimpleColumn::class)
            ->addColumn('statut', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => ChoiceType::class,
                XEditableColumn::PROPERTY => 'statut',
                XEditableColumn::PARAMS => ['choices' => Facture::getStatutChoices()]
            ])
            ->addColumn('Création', 'date', DateTimeColumn::class)
            ->addColumn("Dernière impression", 'latestImpression', DateTimeColumn::class)
            ->addColumn('Montant', 'montant', SimpleColumn::class)
            ->addColumn('Payé', 'montantPaye', SimpleColumn::class)
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
