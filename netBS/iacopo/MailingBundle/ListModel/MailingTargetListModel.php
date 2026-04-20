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

    public function setMailingListId(int $id): void
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
                            return "<span class='badge text-bg-info'>Email</span>";
                        case MailingTarget::TYPE_USER:
                            return "<span class='badge text-bg-primary'>Utilisateur</span>";
                        case MailingTarget::TYPE_UNITE:
                            return "<span class='badge text-bg-success'>Unité</span>";
                        case MailingTarget::TYPE_ROLE:
                            return "<span class='badge text-bg-warning'>Rôle</span>";
                        case MailingTarget::TYPE_LIST:
                            return "<span class='badge text-bg-dark'>Liste</span>";
                        default:
                            return "<span class='badge text-bg-secondary'>Inconnu</span>";
                    }
                }
            ])
            ->addColumn("Destinataire", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    $details = $this->targetResolver->getTargetDetails($target);
                    $display = htmlspecialchars($details['display'], ENT_QUOTES, 'UTF-8');
                    $count = (int)$details['count'];
                    $emails = array_map(function($email) {
                        return htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
                    }, $details['emails']);
                    $emailsList = implode("\n", $emails);

                    $countBadge = $count > 0
                        ? "<span class='badge text-bg-secondary ms-2'>{$count} adresses</span>"
                        : "<span class='badge text-bg-warning ms-2'>0 adresses</span>";

                    if ($count > 0) {
                        $title = htmlspecialchars($emailsList, ENT_QUOTES, 'UTF-8');
                        return "<span title='{$title}' style='cursor: help;' data-bs-toggle='tooltip' data-placement='right'>{$display}{$countBadge}</span>";
                    }

                    return "{$display}{$countBadge}";
                }
            ])
            ->addColumn("Actions", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    $editUrl = htmlspecialchars($this->router->generate('iacopo.mailing.target.edit', ['id' => $target->getId()]), ENT_QUOTES, 'UTF-8');
                    $deleteUrl = htmlspecialchars($this->router->generate('iacopo.mailing.target.delete', ['id' => $target->getId()]), ENT_QUOTES, 'UTF-8');

                    return "
                        <a href=\"{$deleteUrl}\" class=\"btn btn-sm btn-danger\" title=\"Supprimer\"
                           data-turbo-method=\"post\" data-turbo-confirm=\"Supprimer ce destinataire ?\">
                            <i class=\"fas fa-trash\"></i>
                        </a>
                    ";
                }
            ]);
    }
}
