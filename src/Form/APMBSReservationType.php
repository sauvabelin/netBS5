<?php

namespace App\Form;

use App\Entity\APMBSReservation;
use NetBS\CoreBundle\Form\Type\SwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class APMBSReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('comment', TextareaType::class, ['label' => 'Remarques'])
            ->add('blockStartDay', SwitchType::class, ['label' => 'Bloquer le jour de début'])
            ->add('blockEndDay', SwitchType::class, ['label' => 'Bloquer le jour de fin'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => APMBSReservation::class
        ]);
    }
}