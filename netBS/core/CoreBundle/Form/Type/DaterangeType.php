<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Model\Daterange;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DaterangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('biggerThan', DatepickerType::class, $options['gt_options'])
            ->add('lowerThan', DatepickerType::class, $options['lt_options'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefaults(array(
                'data_class'    => Daterange::class,
                'lt_options'    => ['label' => "Jusqu'à", 'required' => false],
                'gt_options'    => ['label' => "Depuis", 'required' => false]
            ));
    }
}
