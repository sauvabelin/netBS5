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
                'label'      => 'Groupes publiés',
                'empty_text' => 'Choisissez au moins une condition.',
            ])
            ->add('sousGroupes', ChoiceType::class, [
                'label'   => 'Membres du groupe LDAP',
                'choices' => DirectoryPublicationRule::getSousGroupesChoices(),
            ])
            ->add('sousGroupesRuleJson', RuleType::class, [
                'context'    => 'attribution_sous_groupe',
                'label'      => 'Sous-groupes dont les membres sont ajoutés (mode « selon une règle »)',
                'empty_text' => 'Aucune condition : à définir pour le mode « selon une règle ».',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DirectoryPublicationRule::class]);
    }
}
