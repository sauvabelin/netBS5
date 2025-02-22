<?php

namespace App\ListModel;

use App\Entity\Intendant;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class IntendantList extends BaseListModel
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository(Intendant::class)->findAll();
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
        return "app.intendants";
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
            ->addColumn("Email", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "email",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Téléphone", null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => "phone",
                XEditableColumn::TYPE_CLASS => TextType::class
            ])
            ->addColumn("Utilisateur", null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => AjaxSelect2DocumentType::class,
                XEditableColumn::PROPERTY   => 'user',
                XEditableColumn::PARAMS     => [
                    'class'     => $this->getSecureConfig()->getUserClass(),
                    'multiple'  => false
                ]
            ])
        ;
    }
}