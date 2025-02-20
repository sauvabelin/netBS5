<?php

namespace App\ListModel;

use App\Entity\CabaneTimePeriod;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

class CabaneTimePeriodsList extends BaseListModel
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('App:CabaneTimePeriod')->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return CabaneTimePeriod::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "app.cabane_time_period";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "nom",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Début", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(CabaneTimePeriod $period) {
                    return $period->getTimeStart()->format('H:i');
                }
            ])
            ->addColumn("Fin", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(CabaneTimePeriod $period) {
                    return $period->getTimeEnd()->format('H:i');
                }
            ])
        ;
    }
}