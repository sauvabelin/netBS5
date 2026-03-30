<?php

namespace Ovesco\FacturationBundle\Form;

use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\MergeCreancesToFacture;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MergeCreancesToFactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('compteToUse', EntityType::class, ['label' => 'Compte à utiliser', 'class' => Compte::class])
            ->add('factureModel', EntityType::class, [
                'label' => 'Modèle de facture',
                'class' => FactureModel::class,
                'required' => false,
                'placeholder' => 'Automatique (selon règles)',
            ])
            ->add('creanceIds', HiddenType::class)
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => MergeCreancesToFacture::class
        ]);
    }
}
