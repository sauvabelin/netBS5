<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Rule\EntityTargets;
use NetBS\CoreBundle\Rule\InvalidRuleException;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleGlossary;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edits one rule as JSON in a hidden input driven by the rule-builder Stimulus controller.
 * Model data: the rule JSON (canonical), or null when the rule has no condition.
 */
class RuleType extends AbstractType
{
    const EXPERT_DENIED = 'Seul un administrateur peut modifier une règle experte.';

    public function __construct(
        private RuleRegistry $registry,
        private RuleValidator $validator,
        private RuleDescriber $describer,
        private RuleGlossary $glossary,
        private EntityTargets $targets,
        private Security $security,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn (?string $json) => $json ?? '',
            function (?string $json) {
                try {
                    $rule = Rule::fromJson($json);
                } catch (InvalidRuleException) {
                    return $json;
                }

                return $rule->isEmpty() ? null : $rule->toJson();
            },
        ));

        $initial = null;
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$initial) {
            $initial = $event->getData();
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use (&$initial, $options) {
            $form = $event->getForm();
            $json = $event->getData();

            foreach ($this->validator->validate($json, $options['context'], $options['allow_empty']) as $message) {
                $form->addError(new FormError($message));
            }

            if (!$this->security->isGranted('ROLE_ADMIN') && $this->changesExpert($json, $initial)) {
                $form->addError(new FormError(self::EXPERT_DENIED));
            }
        });
    }

    /** True when the submitted rule is expert and differs from the expert rule the field started with. */
    private function changesExpert(?string $submitted, ?string $initial): bool
    {
        try {
            $rule = Rule::fromJson($submitted);
        } catch (InvalidRuleException) {
            return false;
        }
        if ($rule->mode !== Rule::EXPERT || $rule->isEmpty()) {
            return false;
        }

        try {
            $before = Rule::fromJson($initial);
        } catch (InvalidRuleException) {
            return true;
        }

        return $before->mode !== Rule::EXPERT || $before->expression !== $rule->expression;
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $context = $this->registry->context($options['context']);
        $json    = $form->getData();

        $blocks = [];
        foreach ($this->registry->blocksFor($context->id()) as $id => $block) {
            $params = [];
            foreach ($block->params() as $name => $param) {
                $params[$name] = [
                    'kind'      => $param->kind,
                    'choices'   => $param->choices,
                    'min'       => $param->min,
                    'max'       => $param->max,
                    'target'    => $param->target,
                    'ajaxClass' => $param->kind === RuleParam::ENTITIES ? base64_encode($this->targets->classFor($param->target)) : null,
                ];
            }
            $blocks[$id] = ['label' => $block->label(), 'negatedLabel' => $block->negatedLabel(), 'params' => $params];
        }

        $view->vars['rule_context']    = $context;
        $view->vars['rule_glossary']   = $this->glossary->variables($context);
        $view->vars['rule_empty_text'] = $options['empty_text'];
        $view->vars['rule_sentence']   = $this->describer->describe(is_string($json) ? $json : null, $context->id());
        $view->vars['rule_expert']     = $this->isExpert(is_string($json) ? $json : null);
        $view->vars['rule_config']    = json_encode([
            'blocks'    => $blocks,
            'canExpert' => $this->security->isGranted('ROLE_ADMIN'),
            'emptyText' => $options['empty_text'],
            'example'   => $context->example(),
            'labels'    => $this->entityLabels(is_string($json) ? $json : null),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function isExpert(?string $json): bool
    {
        try {
            return Rule::fromJson($json)->mode === Rule::EXPERT;
        } catch (InvalidRuleException) {
            return false;
        }
    }

    /** @return array<string,string> id => label, for entity ids already in the rule */
    private function entityLabels(?string $json): array
    {
        try {
            $rule = Rule::fromJson($json);
        } catch (InvalidRuleException) {
            return [];
        }

        $labels = [];
        foreach ($rule->conditions() as $condition) {
            foreach ($this->registry->block($condition->block)?->params() ?? [] as $name => $param) {
                $ids = $condition->params[$name] ?? null;
                if ($param->kind === RuleParam::ENTITIES && is_array($ids)) {
                    foreach ($this->targets->labels($param->target, $ids) as $id => $label) {
                        $labels[$param->target . ':' . $id] = $label;
                    }
                }
            }
        }

        return $labels;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('context');
        $resolver->setAllowedTypes('context', 'string');
        $resolver->setDefaults([
            'allow_empty'     => true,
            // shown in place of the conditions when there is none: what an empty rule means here
            'empty_text'      => 'Aucune condition.',
            'required'        => false,
            'error_bubbling'  => false,
        ]);
        $resolver->setAllowedTypes('allow_empty', 'bool');
        $resolver->setAllowedTypes('empty_text', 'string');
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'rule';
    }
}
