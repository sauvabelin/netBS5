<?php

namespace NetBS\SecureBundle\ListModel;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\ListModel\Action\IconAction;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UsersList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait, SecureConfigTrait, FichierConfigTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        $username   = $this->getParameter('username');
        $query      = $this->entityManager->getRepository($this->getManagedItemsClass())->createQueryBuilder('u');

       /*
        if(empty($username)) return [];

        if(strpos($username, "%") !== false)
            $query->andWhere($query->expr()->like('u.username', ':username'));
        else
            $query->andWhere($query->expr()->eq('u.username', ':username'));
        */

        return $query
            //->setParameter('username', $username)
            ->getQuery()
            ->getResult();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('username');
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getSecureConfig()->getUserClass();
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.secure.users';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom d'utilisateur", function(BaseUser $user) {

                $html   = $user->getUsername();
                if(!$user->getIsActive())
                    $html .= " <span class='label label-danger'>Désactivé</span>";
                return $html;
            }, SimpleColumn::class)
            ->addColumn("E-mail", 'email', SimpleColumn::class)
            ->addColumn("Compte activé", null, XEditableColumn::class, array(
                XEditableColumn::PROPERTY   => 'isActive',
                XEditableColumn::TYPE_CLASS => SwitchType::class,
            ))
            ->addColumn('Création', 'dateAdded', DateTimeColumn::class)
            ->addColumn("Actions", null,ActionColumn::class, array(
                ActionColumn::ACTIONS_KEY   => [
                    new ActionItem(IconAction::class, [
                        LinkAction::TITLE   => "Editer l'utilisateur",
                        LinkAction::ROUTE   => function(BaseUser $user) {
                            return $this->router->generate('netbs.secure.user.edit_user', array('id' => $user->getId()));
                        }
                    ]),
                    new ActionItem(IconAction::class, [
                        LinkAction::THEME   => "danger",
                        IconAction::ICON    => "fas fa-times",
                        LinkAction::TITLE   => "Supprimer l'utilisateur",
                        LinkAction::ROUTE   => function(BaseUser $user) {
                            return $this->router->generate('netbs.secure.user.delete_user', array('id' => $user->getId()));
                        },
                        LinkAction::ATTRS   => 'onclick="return confirm(\'Etes-vous sûr? Tout ce qui est lié à cet utilisateur (listes, export...) sera perdu!\')"'
                    ])
                ]
            ))
        ;
    }
}
