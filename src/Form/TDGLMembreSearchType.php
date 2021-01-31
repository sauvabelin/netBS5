<?php

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use NetBS\FichierBundle\Form\Search\SearchBaseMembreInformationType as base;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Model\TDGLMembreSearch;

class TDGLMembreSearchType extends base
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->add('totem', TextType::class, ["label" => "Totem", 'required' => false]);
        $builder->add('anciens', AncienType::class, ["label" => "Inclure les anciens", 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TDGLMembreSearch::class
        ));
    }
}
