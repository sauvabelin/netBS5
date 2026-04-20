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
        // Priority -1: run after Symfony's own validation so we only add errors to fields that passed validator checks.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            if ($form->getParent() !== null) {
                return;
            }
            $this->enforceNotNullColumns($form);
        }, -1);
    }

    private function enforceNotNullColumns(FormInterface $rootForm): void
    {
        $dataClass = $rootForm->getConfig()->getDataClass();
        if (!$dataClass) {
            return;
        }

        $notNullColumns = $this->getNotNullColumns($dataClass);
        if (empty($notNullColumns)) {
            return;
        }

        foreach ($rootForm->all() as $child) {
            if ($this->shouldSkipNotNullCheck($child, $notNullColumns)) {
                continue;
            }

            $value = $child->getData();
            if ($value === null || $value === '') {
                $child->addError(new FormError('Ce champ est obligatoire.'));
            }
        }
    }

    private function shouldSkipNotNullCheck(FormInterface $child, array $notNullColumns): bool
    {
        if (!$child->getConfig()->getMapped()) {
            return true;
        }
        if (!isset($notNullColumns[$child->getName()])) {
            return true;
        }
        return $child->getErrors()->count() > 0;
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

        $constraints = $this->getPropertyConstraints($parentClass, $form->getName());
        $this->applyConstraintsToView($view, $constraints, $parentClass);
    }

    /**
     * @return object[]
     */
    private function getPropertyConstraints(string $className, string $propertyName): array
    {
        try {
            $metadata = $this->validator->getMetadataFor($className);
        } catch (NoSuchMetadataException $e) {
            return [];
        }

        if (!$metadata->hasPropertyMetadata($propertyName)) {
            return [];
        }

        $constraints = [];
        foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
            foreach ($propertyMetadata->getConstraints() as $constraint) {
                $constraints[] = $constraint;
            }
        }
        return $constraints;
    }

    /**
     * @param object[] $constraints
     */
    private function applyConstraintsToView(FormView $view, array $constraints, string $ownerClass): void
    {
        $hasNotBlank = false;

        foreach ($constraints as $constraint) {
            if (!$this->isInDefaultGroup($constraint, $ownerClass)) {
                continue;
            }

            if ($constraint instanceof Assert\NotBlank) {
                $hasNotBlank = true;
            } elseif ($constraint instanceof Assert\Length) {
                $this->applyLengthConstraint($view, $constraint);
            } elseif ($constraint instanceof Assert\Email && !isset($view->vars['attr']['type'])) {
                $view->vars['attr']['type'] = 'email';
            } elseif ($constraint instanceof Assert\Range) {
                $this->applyRangeConstraint($view, $constraint);
            } elseif ($constraint instanceof Assert\Regex) {
                $view->vars['attr']['pattern'] = $this->stripRegexDelimiters($constraint->pattern);
            }
        }

        if ($hasNotBlank) {
            $view->vars['required'] = true;
        }
    }

    private function applyLengthConstraint(FormView $view, Assert\Length $constraint): void
    {
        if ($constraint->max !== null) {
            $view->vars['attr']['maxlength'] = $constraint->max;
        }
        if ($constraint->min !== null) {
            $view->vars['attr']['minlength'] = $constraint->min;
        }
    }

    private function applyRangeConstraint(FormView $view, Assert\Range $constraint): void
    {
        if ($constraint->min !== null) {
            $view->vars['attr']['min'] = $constraint->min;
        }
        if ($constraint->max !== null) {
            $view->vars['attr']['max'] = $constraint->max;
        }
    }

    private function stripRegexDelimiters(string $pattern): string
    {
        if (preg_match('#^(.)(.+)\1([a-zA-Z]*)$#s', $pattern, $m)) {
            return $m[2];
        }
        return $pattern;
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
