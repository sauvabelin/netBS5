<?php

namespace Ovesco\FacturationBundle\Form;

use Ovesco\FacturationBundle\Entity\FactureModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du modèle'])
            ->add('poids', NumberType::class, ['label' => 'Poids'])
            ->add('applicationRule', TextareaType::class, ['label' => "Règle d'application", 'required' => false])
            ->add('titre', TextType::class, ['label' => 'titre de la facture'])
            ->add('topDescription', TextareaType::class, ['label' => "Texte du haut"])
            ->add('bottomSalutations', TextareaType::class, ['label' => "Texte du bas"])
            ->add('signataire', TextType::class, ['label' => "Signataire"])
            ->add('groupName', TextType::class, ['label' => "Nom du groupe"])
            ->add('rue', TextType::class, ['label' => "Rue"])
            ->add('npaVille', TextType::class, ['label' => "NPA et ville"])
            ->add('cityFrom', TextType::class, ['label' => "Ville date"])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => FactureModel::class]);
    }
}