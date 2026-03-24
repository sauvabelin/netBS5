<?php

namespace Ovesco\FacturationBundle\Form;

use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\MassRappel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MassRappelType extends RappelType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

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
        $resolver->setDefaults(array(
            'data_class' => MassRappel::class
        ));
    }
}
