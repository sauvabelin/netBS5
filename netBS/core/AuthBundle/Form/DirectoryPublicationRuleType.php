<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Form;

use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\CoreBundle\Form\Type\RuleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DirectoryPublicationRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['label' => 'Nom de la règle'])
            ->add('selectionJson', RuleType::class, [
                'context'    => 'groupe',
                'label'      => 'Quels groupes partager ?',
                'empty_text' => 'Choisissez au moins une condition.',
            ])
            ->add('sousGroupes', ChoiceType::class, [
                'label'   => 'Qui est membre du groupe partagé ?',
                'choices' => DirectoryPublicationRule::getSousGroupesChoices(),
            ])
            ->add('sousGroupesRuleJson', RuleType::class, [
                'context'    => 'attribution_sous_groupe',
                'label'      => 'Quels sous-groupes ? (seulement pour « certains sous-groupes »)',
                'empty_text' => 'Aucune condition : à définir si vous avez choisi « certains sous-groupes ».',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DirectoryPublicationRule::class]);
    }
}
