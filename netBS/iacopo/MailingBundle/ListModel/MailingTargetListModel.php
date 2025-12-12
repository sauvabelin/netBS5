<?php

namespace Iacopo\MailingBundle\ListModel;

use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class MailingTargetListModel extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    private $mailingListId;

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
                    if ($target->getType() === MailingTarget::TYPE_EMAIL) {
                        return "<span class='badge badge-info'>Email</span>";
                    } elseif ($target->getType() === MailingTarget::TYPE_USER) {
                        return "<span class='badge badge-primary'>Utilisateur</span>";
                    } else {
                        return "<span class='badge badge-success'>Groupe</span>";
                    }
                }
            ])
            ->addColumn("Destinataire", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingTarget $target) {
                    return $target->getDisplayValue();
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
