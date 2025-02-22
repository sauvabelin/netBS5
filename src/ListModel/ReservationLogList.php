<?php

namespace App\ListModel;

use App\Entity\APMBSReservation;
use App\Entity\ReservationLog;
use NetBS\CoreBundle\ListModel\Action\IconAction;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationLogList extends BaseListModel
{
    use EntityManagerTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $reservation = $this->getParameter('reservation');
        $id = $reservation instanceof APMBSReservation ? $reservation->getId() : $reservation;

        return $this->entityManager->createQueryBuilder()
            ->select('l')
            ->from('App:ReservationLog', 'l')
            ->where('l.reservation = :id')
            ->setParameter('id', $id)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('reservation');
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return ReservationLog::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "app.apmbs.reservation_logs";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Action", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(ReservationLog $log) {
                    $bg = "#3c48c9";
                    if($log->getAction() === ReservationLog::ACCEPTED)
                        $bg = "#12a312";
                    if($log->getAction() === ReservationLog::REFUSED)
                        $bg = "#c92424";
                    if($log->getAction() === ReservationLog::CANCELLED)
                        $bg = "#757575";
                    if ($log->getAction() === ReservationLog::MODIFY || $log->getAction() === ReservationLog::MODIFICATION_ACCEPTED)
                        $bg = "#f0ad4e";

                    return "<span class='badge' style='background:{$bg};color:white'>{$log->getAction()}</span>";
                }
            ])
            ->addColumn("Utilisateur", "username", SimpleColumn::class)
            ->addColumn("Date", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(ReservationLog $log) {
                    return $log->getCreatedAt()->format('d/m/Y H:i');
                }
            ])
            ->addColumn("Détails", "payload", ClosureColumn::class, [
                ClosureColumn::CLOSURE => function($payload) {
                    return "<pre>{$payload}</pre>";
                }
            ])
            ->addColumn("Remarques", null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY   => 'comment'
            ])
        ;
    }
}