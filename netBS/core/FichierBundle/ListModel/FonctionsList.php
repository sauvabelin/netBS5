<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\SecureBundle\Entity\Role;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class FonctionsList extends BaseListModel
{
    use EntityManagerTrait, FichierConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from($this->getManagedItemsClass(), 'f')
            ->orderBy('f.poids', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getFonctionClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.fonctions';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY   => 'nom'
            ))
            ->addColumn('Abbreviation', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY   => 'abbreviation'
            ))
            ->addColumn('Poids', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY   => 'poids'
            ))
            ->addColumn('Autorisation liées', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PROPERTY   => 'roles',
                XEditableColumn::PARAMS     => [
                    'class'     => Role::class,
                    'multiple'  => true
                ]
            ))
            ->addColumn('Remarques', null, XEditableColumn::class, array(
                XEditableColumn::TYPE_CLASS => TextareaType::class,
                XEditableColumn::PROPERTY   => 'remarques'
            ))
        ;
    }
}