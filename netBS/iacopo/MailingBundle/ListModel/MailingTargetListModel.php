<?php

namespace Iacopo\MailingBundle\ListModel;

use Iacopo\MailingBundle\Entity\MailingTarget;
use Iacopo\MailingBundle\Service\MailingTargetResolver;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class MailingTargetListModel extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    private $mailingListId;
    private $targetResolver;

    public function __construct(MailingTargetResolver $targetResolver)
    {
        $this->targetResolver = $targetResolver;
    }

    public function setMailingListId($id)
    {
        $this->mailingListId = $id;
    }

    protected function buildItemsList()
    {
        if (!$this->mailingListId) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MailingTarget::class, 't')
            ->where('t.mailingList = :listId')
            ->setParameter('listId', $this->mailingListId)
            ->orderBy('t.type', 'ASC')
            ->addOrderBy('t.targetEmail', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getManagedItemsClass()
    {
        return MailingTarget::class;
    }

    public function getAlias()
    {
        return "iacopo.mailing.targets";
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Type", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    switch ($target->getType()) {
                        case MailingTarget::TYPE_EMAIL:
                            return "<span class='badge badge-info'>Email</span>";
                        case MailingTarget::TYPE_USER:
                            return "<span class='badge badge-primary'>Utilisateur</span>";
                        case MailingTarget::TYPE_UNITE:
                            return "<span class='badge badge-success'>Unité</span>";
                        case MailingTarget::TYPE_ROLE:
                            return "<span class='badge badge-warning'>Rôle</span>";
                        case MailingTarget::TYPE_LIST:
                            return "<span class='badge badge-dark'>Liste</span>";
                        default:
                            return "<span class='badge badge-secondary'>Inconnu</span>";
                    }
                }
            ])
            ->addColumn("Destinataire", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    $details = $this->targetResolver->getTargetDetails($target);
                    $display = htmlspecialchars($details['display']);
                    $count = $details['count'];
                    $emails = array_map('htmlspecialchars', $details['emails']);
                    $emailsList = implode("\n", $emails);

                    $countBadge = $count > 0
                        ? "<span class='badge badge-secondary ml-2'>{$count}</span>"
                        : "<span class='badge badge-warning ml-2'>0</span>";

                    if ($count > 0) {
                        $title = htmlspecialchars($emailsList);
                        return "<span title='{$title}' style='cursor: help;' data-toggle='tooltip' data-placement='right'>{$display}{$countBadge}</span>";
                    }

                    return "{$display}{$countBadge}";
                }
            ])
            ->addColumn("Actions", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    $editUrl = $this->router->generate('iacopo.mailing.target.edit', ['id' => $target->getId()]);
                    $deleteUrl = $this->router->generate('iacopo.mailing.target.delete', ['id' => $target->getId()]);

                    return "
                        <form method=\"post\" action=\"{$deleteUrl}\" style=\"display:inline\" onsubmit=\"return confirm('Supprimer ce destinataire ?')\">
                            <button type=\"submit\" class=\"btn btn-sm btn-danger\" title=\"Supprimer\">
                                <i class=\"fas fa-trash\"></i>
                            </button>
                        </form>
                    ";
                }
            ]);
    }
}
