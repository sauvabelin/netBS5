<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Rappel;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureRappelsList extends BaseListModel
{
    use EntityManagerTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $facture = $this->getParameter('facture');
        return $facture instanceof Facture
            ? $facture->getRappels()
            : $this->entityManager->getRepository('OvescoFacturationBundle:Rappel')
                ->findBy(['facture' => $facture]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('facture');
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Rappel::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.facture_rappels';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Date", 'date', DateTimeColumn::class)
            ->addColumn("Date d'impression", 'dateImpression', DateTimeColumn::class)
            ->addColumn('Remarques', 'remarques', SimpleColumn::class)
            ->addColumn('', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class
                ]
            ])
        ;
    }
}
