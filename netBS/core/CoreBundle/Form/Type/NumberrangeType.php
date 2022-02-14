<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Model\Numberrange;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberrangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('biggerThan', NumberType::class, $options['gt_options'])
            ->add('lowerThan', NumberType::class, $options['lt_options'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Numberrange::class,
            'lt_options'    => ['label' => 'Plus petit que'],
            'gt_options'    => ['label' => 'Plus grand que']
        ));
    }
}