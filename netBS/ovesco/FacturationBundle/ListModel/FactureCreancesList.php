<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureCreancesList extends BaseListModel
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
            ? $facture->getCreances()
            : $this->entityManager->getRepository('OvescoFacturationBundle:Creance')
            ->findBy(['facture' => $facture]);
    }

    public function getManagedItemsClass()
    {
        return Creance::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('facture');
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.facture_creances';
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Numéro', 'id', SimpleColumn::class)
            ->addColumn('Titre', 'titre', SimpleColumn::class)
            ->addColumn('Date de création', 'date', DateTimeColumn::class)
            ->addColumn('Montant', 'montant', SimpleColumn::class)
            ->addColumn('Rabais', 'rabais', SimpleColumn::class)
            ->addColumn('Rabais famille', 'rabaisIfInFamille', SimpleColumn::class)
            ->addColumn('Montant effectif', 'actualMontant', SimpleColumn::class)
            ->addColumn('Remarques', 'remarques', SimpleColumn::class)
            ->addColumn('', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class
                ]
            ])
        ;
    }
}
