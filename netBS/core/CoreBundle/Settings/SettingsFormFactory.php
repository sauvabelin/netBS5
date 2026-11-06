<?php

namespace NetBS\CoreBundle\Settings;

use NetBS\CoreBundle\Form\Type\RuleType;
use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Builds one form per section of the Paramètres page from the declarations. Submitted values
 * are checked with the same SettingValidator as every other write path, inside the form, so
 * an invalid section is never written at all.
 */
class SettingsFormFactory
{
    const RULE_NOT_SET = "Aucune condition : le comportement par défaut s'applique (voir description).";

    public function __construct(
        private FormFactoryInterface $formFactory,
        private SettingsRegistry $registry,
        private SettingValidator $validator,
        private ParameterManager $parameters,
    ) {
    }

    public static function formName(string $section): string
    {
        return 'settings_' . $section;
    }

    public static function fieldName(SettingDefinition $d): string
    {
        return str_replace('.', '__', $d->id());
    }

    public function create(string $section): FormInterface
    {
        $builder = $this->formFactory->createNamedBuilder(self::formName($section), FormType::class, null, [
            'csrf_token_id' => self::formName($section),
        ]);

        $definitions = $this->registry->inSection($section);

        foreach ($definitions as $definition) {
            [$type, $options] = $this->fieldType($definition);
            $builder->add(self::fieldName($definition), $type, $options + [
                'label'    => $definition->label,
                'help'     => $definition->help,
                'required' => true,
                'data'     => $this->initialData($definition),
            ]);
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($definitions) {
            $form = $event->getForm();
            foreach ($definitions as $definition) {
                $field = $form->get(self::fieldName($definition));
                // RuleType validates itself (including the expert-mode gate).
                if ($definition->type !== SettingType::Rule && $field->isSynchronized() && ($error = $this->validator->validate($definition, self::toRaw($field->getData()))) !== null) {
                    $field->addError(new FormError($error));
                }
            }
        });

        return $builder->getForm();
    }

    /**
     * The values a valid submitted section form writes, as namespace => [key => raw string].
     *
     * @return list<array{string,string,string}> namespace, key, value
     */
    public function submittedValues(string $section, FormInterface $form): array
    {
        $values = [];

        foreach ($this->registry->inSection($section) as $definition) {
            $values[] = [$definition->namespace, $definition->key, self::toRaw($form->get(self::fieldName($definition))->getData())];
        }

        return $values;
    }

    private function fieldType(SettingDefinition $d): array
    {
        return match ($d->type) {
            SettingType::Text   => [TextType::class, ['attr' => ['maxlength' => 255]]],
            SettingType::Int    => [IntegerType::class, [
                'invalid_message' => 'Nombre entier attendu.',
                'attr'            => array_filter(['min' => $d->min, 'max' => $d->max], fn($v) => $v !== null),
            ]],
            // shown in plain text: admins hand these passwords out, they are not personal
            SettingType::Secret => [TextType::class, ['attr' => ['maxlength' => 255, 'autocomplete' => 'off']]],
            SettingType::Choice => [ChoiceType::class, ['choices' => array_flip($d->choices)]],
            // an empty rule deletes the row, so no condition means the legacy logic
            SettingType::Rule   => [RuleType::class, ['context' => $d->context, 'required' => false, 'empty_text' => self::RULE_NOT_SET]],
        };
    }

    private function initialData(SettingDefinition $d): mixed
    {
        $raw = $this->parameters->getValue($d->namespace, $d->key);

        return $d->type === SettingType::Int ? (preg_match('/^-?\d+$/', (string) $raw) ? (int) $raw : null) : $raw;
    }

    private static function toRaw(mixed $data): ?string
    {
        return $data === null ? null : (string) $data;
    }
}
