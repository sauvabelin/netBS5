<?php

namespace App\Form\Extension;

use NetBS\FichierBundle\Form\Personne\MembreType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MembreExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('totem', TextType::class, array('label' => 'Totem'));
    }

    public static function getExtendedTypes() : iterable
    {
        return [MembreType::class];
    }
}
