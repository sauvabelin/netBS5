<?php

namespace Ovesco\FacturationBundle\Searcher;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\Model\BaseSearcher;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Form\SearchPaiementType;
use Ovesco\FacturationBundle\ListModel\Column\FactureCreancesColumn;
use Ovesco\FacturationBundle\Model\SearchPaiement;

class PaiementSearcher extends BaseSearcher
{
    use RouterTrait;

    /**
     * Returns the search form type class
     * @return string
     */
    public function getSearchType()
    {
        return SearchPaiementType::class;
    }

    /**
     * Returns an object used to render form, which will contain search data
     * @return object
     */
    public function getSearchObject()
    {
        return new SearchPaiement();
    }

    /**
     * Returns the twig template used to render the form. A variable casually named 'form' will be available
     * for you to use
     * @return string
     */
    public function getFormTemplate()
    {
        return "@OvescoFacturation/paiement/search_paiement.html.twig";
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Paiement::class;
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(\NetBS\ListBundle\Model\ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Facture', 'facture', HelperColumn::class)
            ->addColumn('Créances de la facture', 'facture.creances', FactureCreancesColumn::class)
            ->addColumn('Exécuté le', 'date', DateTimeColumn::class)
            ->addColumn('Montant du paiement', 'montant', SimpleColumn::class)
            ->addColumn('Montant de la facture', 'facture.montant', SimpleColumn::class)
            ->addColumn('Remarques', 'remarques', SimpleColumn::class)
            ->addColumn('', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class,
                    ModalAction::class => [
                        ModalAction::ROUTE => function(Paiement $paiement) { return $this->router->generate('ovesco.facturation.paiement.modal_details', ['id' => $paiement->getId()]); },
                        ModalAction::ICON => 'fas fa-info'
                    ]
                ],
            ])

        ;
    }
}
