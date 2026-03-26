<?php

namespace Ovesco\FacturationBundle\Form;

use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\MassAssignModel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MassAssignModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('factureModel', EntityType::class, [
                'label' => 'Modèle de facture',
                'class' => FactureModel::class,
                'required' => false,
                'placeholder' => 'Automatique (selon règles)',
            ])
            ->add('selectedIds', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MassAssignModel::class,
        ]);
    }
}
