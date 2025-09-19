<?php

namespace App\ListModel;

use App\Controller\APMBSController;
use App\Entity\APMBSReservation;
use NetBS\CoreBundle\ListModel\Action\IconAction;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CabaneReservationsList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $cabaneId = $this->getParameter('cabaneId');
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(APMBSReservation::class, 'r')
            ->where('r.cabane = :id')
            ->setParameter('id', $cabaneId)
            ->addOrderBy('r.status', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('cabaneId');
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return APMBSReservation::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "app.apmbs.cabane.reservations";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Status", null, ClosureColumn:: class, [
                ClosureColumn::CLOSURE => function(APMBSReservation $reservation) {
                    $bg = APMBSController::getHighlightColor($reservation)[1];
                    return "<span class='badge' style='background:{$bg};color:white'>{$reservation->getStatus()}</span>";
                }
            ])
            ->addColumn("Début", null, ClosureColumn:: class, [
                ClosureColumn::CLOSURE => function(APMBSReservation $reservation) {
                    return $reservation->getStart()->format('d/m/Y H:i');
                }
            ])
            ->addColumn("Fin", null, ClosureColumn:: class, [
                ClosureColumn::CLOSURE => function(APMBSReservation $reservation) {
                    return $reservation->getEnd()->format('d/m/Y H:i');
                }
            ])
            ->addColumn("Groupe", "unite", SimpleColumn::class)
            ->addColumn("Actions", null,ActionColumn::class, array(
                ActionColumn::ACTIONS_KEY   => [
                    new ActionItem(IconAction::class, [
                        LinkAction::TITLE   => "Voir la réservation",
                        LinkAction::ROUTE   => function(APMBSReservation $reservation) {
                            return $this->router->generate('sauvabelin.apmbs.reservation', array('id' => $reservation->getId()));
                        }
                    ]),
                ]
            ))
        ;
    }
}