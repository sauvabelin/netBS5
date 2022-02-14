<?php

namespace App\Form;

use NetBS\FichierBundle\Form\Personne\MembreType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

class MembreTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('numeroBS', NumberType::class, array('label' => 'Numéro BS'));
    }

    public static function getExtendedTypes(): Iterable
    {
        return [MembreType::class];
    }
}
