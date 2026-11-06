<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleValidator;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Service\Attribute\Required;

class FactureModelsList extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    private RuleDescriber $ruleDescriber;

    private RuleValidator $ruleValidator;

    #[Required]
    public function setRuleServices(RuleDescriber $ruleDescriber, RuleValidator $ruleValidator): void
    {
        $this->ruleDescriber = $ruleDescriber;
        $this->ruleValidator = $ruleValidator;
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    /**
     * A short badge, with the rule spelled out in its tooltip. Tooltips render
     * HTML, so each line is escaped twice: once for the attribute, once for
     * the tooltip body.
     */
    private function ruleBadge(string $variant, string $label, array $lines): string
    {
        $escape = fn(string $text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $title = $escape(implode('<br>', array_map($escape, $lines)));

        return "<span class='badge text-bg-{$variant}' data-bs-toggle='tooltip' title='{$title}' style='cursor:help;'>{$label}</span>";
    }

    protected function buildItemsList()
    {
        return $this->entityManager->getRepository(FactureModel::class)
            ->createQueryBuilder('m')->orderBy('m.poids', 'DESC')->getQuery()->getResult();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return FactureModel::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.facture_models';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'name',
            ])
            ->addColumn('Signataire', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'signataire',
            ])
            ->addColumn('Expéditeur', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'groupName',
            ])
            ->addColumn('Rue', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'rue',
            ])
            ->addColumn('NPA et ville', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'npaVille',
            ])
            ->addColumn('Lieu', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY => 'cityFrom',
            ])
            ->addColumn('Utilisé quand', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(FactureModel $model) {
                    $json = $model->getRuleJson();
                    if ($json === null) return '<span class="badge text-bg-secondary">Toujours</span>';

                    $lines = [$this->ruleDescriber->describe($json, 'facture')];
                    if ($model->getRule() === null) $lines[] = '(ancienne expression, à remplacer par des conditions)';

                    $errors = $this->ruleValidator->validate($json, 'facture');
                    if ($errors) return $this->ruleBadge('danger', 'Erreur', array_merge($lines, $errors));

                    return $this->ruleBadge('warning', 'Conditions', $lines);
                }
            ])
            ->addColumn('Poids', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => NumberType::class,
                XEditableColumn::PROPERTY => 'poids',
            ])
            ->addColumn('Actions', null, ActionColumn::class, [
                ActionColumn::ACTIONS_KEY => [
                    RemoveAction::class,
                    new ActionItem(ModalAction::class, [
                        ModalAction::ROUTE => function(FactureModel $model) { return $this->router->generate('ovesco.facturation.facture_model.edit_modal', ['id' => $model->getId()]); },
                        ModalAction::ICON => 'fas fa-edit',
                    ]),
                    new ActionItem(ModalAction::class, [
                        ModalAction::ROUTE => function(FactureModel $model) { return $this->router->generate('ovesco.facturation.facture_model.duplicate', ['id' => $model->getId()]); },
                        ModalAction::ICON => 'fas fa-copy',
                    ]),
                ]
            ])
            ;
    }
}
