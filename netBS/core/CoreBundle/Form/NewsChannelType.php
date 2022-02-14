<?php

namespace NetBS\CoreBundle\Form;

use NetBS\CoreBundle\Entity\NewsChannel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsChannelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("nom", TextType::class, ['label' => "Nom du channel"])
            ->add('color', ColorType::class, ['label' => 'Couleur'])
            ->add("postRule", TextareaType::class, ['label' => "Règle d'écriture (expression language)"])
            ->add("readRule", TextareaType::class, ['label' => "Règle de lecture (expression language)"])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => NewsChannel::class
        ));
    }
}
