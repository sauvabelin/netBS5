<?php

namespace App\ListModel;

use App\Entity\Intendant;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationIntendantsList extends BaseListModel
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('App:APMBSReservation')->find($this->getParameter('reservation'))->getIntendants();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Intendant::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "app.apmbs.reservation.intendants";
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('reservation');
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom", 'nom', SimpleColumn::class)
            ->addColumn("Email", 'email', SimpleColumn::class)
            ->addColumn("Téléphone", 'phone', SimpleColumn::class)
        ;
    }
}