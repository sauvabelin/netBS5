<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupeAttributionsList extends BaseListModel
{
    const GROUPE_ID = 'groupeId';

    use EntityManagerTrait, FichierConfigTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::GROUPE_ID);
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $query  = $this->entityManager->createQueryBuilder();

        $query->select('a')
            ->from($this->getManagedItemsClass(), 'a')
            ->join('a.groupe', 'g')
            ->where('g.id = :id')
            ->setParameter(':id', $this->getParameter(self::GROUPE_ID))
            ->andWhere('a.dateDebut < CURRENT_TIMESTAMP()')
            ->andWhere($query->expr()->orX(
                $query->expr()->isNull('a.dateFin'),
                $query->expr()->gt('a.dateFin', 'CURRENT_TIMESTAMP()')
            ));

        $result = $query
            ->getQuery()
            ->execute();

        usort($result, BaseAttribution::getSortFunction());

        return $result;
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
        return 'netbs.fichier.groupe.attributions';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', 'membre', HelperColumn::class)
            ->addColumn('Fonction', null, XEditableColumn::class, array(
                XEditableColumn::PROPERTY   => 'fonction',
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PARAMS     => [
                    'class' => $this->getFichierConfig()->getFonctionClass()
                ]
            ))
            ->addColumn('Depuis le', 'dateDebut', DateTimeColumn::class)
            ->addColumn('Remarques', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'remarques',
                XEditableColumn::TYPE_CLASS => TextareaType::class
            ])
        ;
    }
}