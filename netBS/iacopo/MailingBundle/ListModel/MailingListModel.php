<?php

namespace Iacopo\MailingBundle\ListModel;

use Iacopo\MailingBundle\Entity\MailingList;
use NetBS\CoreBundle\ListModel\Action\LinkAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class MailingListModel extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(MailingList::class, 'm')
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return MailingList::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "iacopo.mailing.lists";
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Nom", "name", SimpleColumn::class)
            ->addColumn("Adresse de base", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingList $list) {
                    $address = htmlspecialchars($list->getBaseAddress(), ENT_QUOTES, 'UTF-8');
                    return "<code>{$address}</code>";
                }
            ])
            ->addColumn("Description", "description", SimpleColumn::class)
            ->addColumn("Statut", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingList $list) {
                    $checked = $list->isActive() ? 'checked' : '';
                    $label = htmlspecialchars($list->isActive() ? 'Active' : 'Inactive', ENT_QUOTES, 'UTF-8');
                    $badgeClass = $list->isActive() ? 'text-bg-success' : 'text-bg-secondary';
                    $id = (int)$list->getId();
                    return "
                        <div class='custom-control custom-switch' onclick='event.stopPropagation()'>
                            <input type='checkbox'
                                   class='custom-control-input'
                                   id='toggle-{$id}'
                                   data-list-id='{$id}'
                                   data-action='change->mailing#toggleActive'
                                   {$checked}>
                            <label class='custom-control-label' for='toggle-{$id}'>
                                <span class='badge {$badgeClass}' data-label-id='{$id}'>{$label}</span>
                            </label>
                        </div>
                    ";
                }
            ])
            ->addColumn("Destinataires", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingList $list) {
                    $id = (int)$list->getId();
                    $targetCount = (int)$list->getTargets()->count();

                    // Render placeholder badge - actual count loaded via AJAX on hover
                    return "<span class='recipient-count' data-list-id='{$id}' data-bs-toggle='tooltip' title='Survolez pour charger...' data-action='mouseenter->mailing#loadRecipients'>"
                         . "<span class='badge text-bg-secondary'>{$targetCount} cible(s)</span>"
                         . "</span>";
                }
            ])
            ->addColumn("Actions", null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    new ActionItem(LinkAction::class, [
                        LinkAction::TEXT => '<i class="fas fa-edit"></i>',
                        LinkAction::TITLE => 'Modifier',
                        LinkAction::THEME => 'primary',
                        LinkAction::SIZE => 'btn-sm',
                        LinkAction::ROUTE => function(MailingList $list) {
                            return htmlspecialchars($this->router->generate('iacopo.mailing.edit', ['id' => $list->getId()]), ENT_QUOTES, 'UTF-8');
                        }
                    ]),
                    new ActionItem(LinkAction::class, [
                        LinkAction::TEXT => '<i class="fas fa-trash"></i>',
                        LinkAction::TITLE => 'Supprimer',
                        LinkAction::THEME => 'danger',
                        LinkAction::SIZE => 'btn-sm',
                        LinkAction::ATTRS => 'data-turbo-confirm="Êtes-vous sûr de vouloir supprimer cette liste ?"',
                        LinkAction::ROUTE => function(MailingList $list) {
                            return htmlspecialchars($this->router->generate('iacopo.mailing.delete', ['id' => $list->getId()]), ENT_QUOTES, 'UTF-8');
                        }
                    ])
                ]
            ]);
    }
}
