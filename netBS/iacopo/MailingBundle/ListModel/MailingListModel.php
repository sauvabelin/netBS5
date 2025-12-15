<?php

namespace Iacopo\MailingBundle\ListModel;

use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Service\MailingTargetResolver;
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

    private $targetResolver;

    public function __construct(MailingTargetResolver $targetResolver)
    {
        $this->targetResolver = $targetResolver;
    }

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
                    return "<code>{$list->getBaseAddress()}</code>";
                }
            ])
            ->addColumn("Description", "description", SimpleColumn::class)
            ->addColumn("Statut", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingList $list) {
                    $checked = $list->isActive() ? 'checked' : '';
                    $label = $list->isActive() ? 'Active' : 'Inactive';
                    $badgeClass = $list->isActive() ? 'badge-success' : 'badge-secondary';
                    return "
                        <div class='custom-control custom-switch' onclick='event.stopPropagation()'>
                            <input type='checkbox'
                                   class='custom-control-input'
                                   id='toggle-{$list->getId()}'
                                   {$checked}
                                   onchange='toggleListActive({$list->getId()})'>
                            <label class='custom-control-label' for='toggle-{$list->getId()}'>
                                <span class='badge {$badgeClass}' id='label-{$list->getId()}'>{$label}</span>
                            </label>
                        </div>
                    ";
                }
            ])
            ->addColumn("Destinataires", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingList $list) {
                    $targetCount = $list->getTargets()->count();
                    $emailCount = $this->targetResolver->countMailingList($list);
                    $emails = $this->targetResolver->resolveMailingList($list);
                    $emailsList = implode("\n", array_map('htmlspecialchars', $emails));

                    $badge = $emailCount > 0
                        ? "<span class='badge badge-info'>{$emailCount} adresse(s)</span>"
                        : "<span class='badge badge-warning'>0 adresse</span>";

                    $targetBadge = "<span class='badge badge-secondary ml-1'>{$targetCount} cible(s)</span>";

                    if ($emailCount > 0) {
                        $title = htmlspecialchars($emailsList);
                        return "<span title='{$title}' style='cursor: help;' data-toggle='tooltip' data-placement='left'>{$badge}</span>{$targetBadge}";
                    }

                    return "{$badge}{$targetBadge}";
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
                            return $this->router->generate('iacopo.mailing.edit', ['id' => $list->getId()]);
                        }
                    ]),
                    new ActionItem(LinkAction::class, [
                        LinkAction::TEXT => '<i class="fas fa-trash"></i>',
                        LinkAction::TITLE => 'Supprimer',
                        LinkAction::THEME => 'danger',
                        LinkAction::SIZE => 'btn-sm',
                        LinkAction::ATTRS => 'onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cette liste ?\')"',
                        LinkAction::ROUTE => function(MailingList $list) {
                            return $this->router->generate('iacopo.mailing.delete', ['id' => $list->getId()]);
                        }
                    ])
                ]
            ]);
    }
}
