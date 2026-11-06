<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\CoreBundle\Form\Type\RuleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DirectoryComputedGroupType extends AbstractType
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label'       => 'Nom du groupe',
                'constraints' => [new Callback($this->validateName(...))],
            ])
            ->add('description', TextType::class, ['label' => 'Description', 'required' => false])
            ->add('membresJson', RuleType::class, [
                'context'    => 'membre',
                'label'      => 'Membres',
                'empty_text' => 'Choisissez au moins une condition.',
            ]);
    }

    /**
     * The name, as it ends up in lldap, must not be empty nor taken by a
     * reserved name, a role group or another computed group (groupe groups
     * give way: they get a suffix).
     */
    public function validateName(?string $name, ExecutionContextInterface $context): void
    {
        $safe = mb_strtolower(DirectoryProjection::dnSafe(trim((string) $name)));
        if ($safe === '') {
            $context->addViolation('Ce nom ne contient aucun caractère utilisable.');
            return;
        }

        $taken = [...DirectoryProjection::RESERVED_NAMES, ...array_map('mb_strtolower', DirectoryProjection::ROLE_GROUPS)];
        $self = $context->getRoot()->getData();
        foreach ($this->em->getRepository(DirectoryComputedGroup::class)->findAll() as $other) {
            if ($other !== $self) {
                $taken[] = mb_strtolower(DirectoryProjection::dnSafe($other->getName()));
            }
        }
        if (in_array($safe, $taken, true)) {
            $context->addViolation('Ce nom est déjà utilisé par un autre groupe.');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DirectoryComputedGroup::class]);
    }
}
