<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\FichierBundle\Mapping\Personne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SexeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'choices' => [
                'Homme' => Personne::HOMME,
                'Femme' => Personne::FEMME
            ]
        ));
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}