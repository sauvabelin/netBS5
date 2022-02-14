<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Creance;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DebiteurCreancesList extends BaseListModel
{
    use EntityManagerTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $creances = $this->entityManager->getRepository('OvescoFacturationBundle:Creance')
            ->findBy(['debiteurId' => $this->getParameter('debiteurId')]);
        return array_filter($creances, function(Creance $creance) { return $creance->getFacture() === null; });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->isRequired('debiteurId');
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.debiteur_creances';
    }

    public function getManagedItemsClass()
    {
        return Creance::class;
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('numero', 'id', SimpleColumn::class)
            ->addColumn('titre', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'titre',
            ])
            ->addColumn('Date de création', 'date', DateTimeColumn::class)
            ->addColumn('Montant', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY => 'montant',
            ])
            ->addColumn('Rabais', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY => 'rabais',
            ])
            ->addColumn('Rabais si famille', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY => 'rabaisIfInFamille',
            ])
            ->addColumn('', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class
                ]
            ])
        ;
    }
}
