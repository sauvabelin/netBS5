<?php

namespace App\Searcher;

use App\Entity\APMBSReservation;
use App\Form\Search\SearchAPMBSReservationType;
use App\Model\SearchReservation;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\Model\BaseSearcher;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class APMBSReservationSearcher extends BaseSearcher
{
    use FichierConfigTrait, RouterTrait;

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return APMBSReservation::class;
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('cabane', 'cabane.nom', SimpleColumn::class)
            ->addColumn('status', 'status', SimpleColumn::class)
            ->addColumn('début', 'start', DateTimeColumn::class, ['format' => 'd.m.Y H:i'])
            ->addColumn('fin', 'end', DateTimeColumn::class, ['format' => 'd.m.Y H:i'])
            ->addColumn('groupe', 'unite', SimpleColumn::class)
            ->addColumn('email', 'email', SimpleColumn::class)
            ->addColumn('reçue le', 'createdAt', DateTimeColumn::class, ['format' => 'd.m.Y H:i'])
            ->addColumn('actions', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    ModalAction::class  => [
                        LinkAction::ROUTE   => function(APMBSReservation $reservation) {
                            return $this->router->generate('sauvabelin.apmbs_reservations.modal', ['id' => $reservation->getId()]);
                        }
                    ]
                ],
            ])
        ;
    }

    /**
     * Returns the search form type class
     * @return string
     */
    public function getSearchType()
    {
        return SearchAPMBSReservationType::class;
    }

    /**
     * Returns the twig template used to render the form. A variable casually named 'form' will be available
     * for you to use
     * @return string
     */
    public function getFormTemplate()
    {
        return 'reservation/search_reservation.html.twig';
    }

    /**
     * Returns an object used to render form, which will contain search data
     * @return object
     */
    public function getSearchObject()
    {
        return new SearchReservation();
    }
}