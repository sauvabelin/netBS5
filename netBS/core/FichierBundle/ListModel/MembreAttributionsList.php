<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreAttributionsList extends BaseListModel
{
    const MEMBRE_ID = 'membreId';

    use EntityManagerTrait, FichierConfigTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::MEMBRE_ID);
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from($this->getManagedItemsClass(), 'a')
            ->join('a.membre', 'm')
            ->where('m.id = :id')
            ->setParameter('id', $this->getParameter(self::MEMBRE_ID))
            ->orderBy('a.dateDebut', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getAttributionClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.membre.attributions';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            // ->addColumn('Unité', 'groupe', HelperColumn::class)
            ->addColumn('Unité', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'groupe',
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PARAMS     => ['class' => $this->getFichierConfig()->getGroupeClass()]
            ])
            ->addColumn('Fonction', null, XEditableColumn::class, array(
                XEditableColumn::PROPERTY   => 'fonction',
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PARAMS     => ['class' => $this->getFichierConfig()->getFonctionClass()]
            ))
            ->addColumn('Début', null, XEditableColumn::class, array(
                XEditableColumn::PROPERTY   => 'dateDebut',
                XEditableColumn::TYPE_CLASS => DatepickerType::class
            ))
            ->addColumn('Fin', null, XEditableColumn::class, array(
                XEditableColumn::PROPERTY   => 'dateFin',
                XEditableColumn::TYPE_CLASS => DatepickerType::class
            ))
            ->addColumn("Remarques", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'remarques',
                XEditableColumn::TYPE_CLASS => TextareaType::class
            ])
            ->addColumn('Actions', null, ActionColumn::class, ['actions' => [
                RemoveAction::class
            ]])
        ;
    }
}
