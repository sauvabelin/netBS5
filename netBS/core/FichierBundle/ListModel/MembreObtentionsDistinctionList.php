<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreObtentionsDistinctionList extends BaseListModel
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
            ->select('od')
            ->from($this->getManagedItemsClass(), 'od')
            ->join('od.membre', 'm')
            ->where('m.id = :id')
            ->setParameter('id', $this->getParameter(self::MEMBRE_ID))
            ->orderBy('od.date', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getObtentionDistinctionClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.membre.obtentions_distinction';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Distinction', 'distinction.nom', SimpleColumn::class)
            ->addColumn('Date d\'obtention', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'date',
                XEditableColumn::TYPE_CLASS => DatepickerType::class
            ])
            ->addColumn('Actions', null, ActionColumn::class, ['actions' => [
                RemoveAction::class
            ]])
        ;
    }
}