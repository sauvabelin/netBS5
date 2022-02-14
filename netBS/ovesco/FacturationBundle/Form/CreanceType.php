<?php

namespace Ovesco\FacturationBundle\Form;

use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Form\Type\DebiteurType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreanceType extends BaseCreanceType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('debiteur', DebiteurType::class, ['label' => 'debiteur'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => Creance::class
        ]);
    }
}
