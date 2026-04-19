<?php

namespace NetBS\CoreBundle\Form\Extension;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reads entity validation constraints and Doctrine ORM column metadata
 * to bridge server-side rules to HTML5 attributes and catch NOT NULL
 * violations before they hit the database.
 */
class ConstraintToHtmlExtension extends AbstractTypeExtension
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $em;
    private array $shortNameCache = [];

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $em)
    {
        $this->validator = $validator;
        $this->em = $em;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();

            // Only run on root forms, not on each child field
            if ($form->getParent() !== null) {
                return;
            }

            $dataClass = $form->getConfig()->getDataClass();
            if (!$dataClass) {
                return;
            }

            $notNullColumns = $this->getNotNullColumns($dataClass);
            if (empty($notNullColumns)) {
                return;
            }

            foreach ($form->all() as $child) {
                if (!$child->getConfig()->getMapped()) {
                    continue;
                }

                $fieldName = $child->getName();
                if (!isset($notNullColumns[$fieldName])) {
                    continue;
                }

                // Already has validation errors — don't pile on
                if ($child->getErrors()->count() > 0) {
                    continue;
                }

                $value = $child->getData();
                if ($value === null || $value === '') {
                    $child->addError(new FormError('Ce champ est obligatoire.'));
                }
            }
        }, -1); // Low priority: run after Symfony's own validation
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if (!$options['mapped'] || !$form->getParent()) {
            return;
        }

        if ($form->getConfig()->getCompound()) {
            return;
        }

        $parentClass = $this->resolveDataClass($form->getParent());
        if (!$parentClass) {
            return;
        }

        $propertyName = $form->getName();

        // --- Validator constraints → HTML attributes ---

        $hasNotBlank = false;

        try {
            $metadata = $this->validator->getMetadataFor($parentClass);
            if ($metadata->hasPropertyMetadata($propertyName)) {
                $constraints = [];
                foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
                    foreach ($propertyMetadata->getConstraints() as $constraint) {
                        $constraints[] = $constraint;
                    }
                }

                foreach ($constraints as $constraint) {
                    if (!$this->isInDefaultGroup($constraint, $parentClass)) {
                        continue;
                    }

                    if ($constraint instanceof Assert\NotBlank) {
                        $hasNotBlank = true;
                    }

                    if ($constraint instanceof Assert\Length) {
                        if ($constraint->max !== null) {
                            $view->vars['attr']['maxlength'] = $constraint->max;
                        }
                        if ($constraint->min !== null) {
                            $view->vars['attr']['minlength'] = $constraint->min;
                        }
                    }

                    if ($constraint instanceof Assert\Email && !isset($view->vars['attr']['type'])) {
                        $view->vars['attr']['type'] = 'email';
                    }

                    if ($constraint instanceof Assert\Range) {
                        if ($constraint->min !== null) {
                            $view->vars['attr']['min'] = $constraint->min;
                        }
                        if ($constraint->max !== null) {
                            $view->vars['attr']['max'] = $constraint->max;
                        }
                    }

                    if ($constraint instanceof Assert\Regex) {
                        $pattern = $constraint->pattern;
                        if (preg_match('#^(.)(.+)\1([a-zA-Z]*)$#s', $pattern, $m)) {
                            $pattern = $m[2];
                        }
                        $view->vars['attr']['pattern'] = $pattern;
                    }
                }
            }
        } catch (NoSuchMetadataException $e) {
            // No validator metadata for this class
        }

        if ($hasNotBlank) {
            $view->vars['required'] = true;
        }
    }

    /**
     * Returns a map of property names that have NOT NULL columns (excluding
     * auto-generated fields like id and timestampable fields).
     */
    private function getNotNullColumns(string $className): array
    {
        try {
            $meta = $this->em->getClassMetadata($className);
        } catch (\Exception $e) {
            return [];
        }

        $skipFields = ['id', 'createdAt', 'updatedAt', 'deletedAt'];
        $notNull = [];

        foreach ($meta->getFieldNames() as $fieldName) {
            if (in_array($fieldName, $skipFields, true)) {
                continue;
            }

            $mapping = $meta->getFieldMapping($fieldName);
            $nullable = $mapping['nullable'] ?? false;

            if (!$nullable) {
                $notNull[$fieldName] = true;
            }
        }

        return $notNull;
    }

    private function resolveDataClass(FormInterface $form): ?string
    {
        $config = $form->getConfig();
        $dataClass = $config->getDataClass();

        if ($dataClass) {
            return $dataClass;
        }

        $parent = $form->getParent();
        if ($parent) {
            return $this->resolveDataClass($parent);
        }

        return null;
    }

    private function isInDefaultGroup(object $constraint, string $className): bool
    {
        $groups = $constraint->groups;
        $shortName = $this->shortNameCache[$className] ??= (new \ReflectionClass($className))->getShortName();

        return in_array('Default', $groups, true)
            || in_array($shortName, $groups, true);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
