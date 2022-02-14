<?php

namespace NetBS\SecureBundle\ListModel;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\ListModel\Action\IconAction;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\ArrayColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AutorisationsList extends BaseListModel
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository($this->getManagedItemsClass())->findAll();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getSecureConfig()->getAutorisationClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.secure.autorisations';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Utilisateur', 'user', HelperColumn::class)
            ->addColumn('Groupe', 'groupe', HelperColumn::class)
            ->addColumn('Roles', 'roles', ArrayColumn::class, [
                ArrayColumn::LABEL      => function($items) {return count($items) . " role(s)";},
                ArrayColumn::FORMATTING => function(BaseRole $role) {
                    return $role->getRole();
                }
            ])
            ->addColumn('Actions', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY   => [
                    RemoveAction::class
                ]
            ])
        ;
    }
}