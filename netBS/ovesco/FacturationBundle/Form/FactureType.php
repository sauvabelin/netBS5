<?php

namespace Ovesco\FacturationBundle\Form;

use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\Type\DebiteurType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('date', DateType::class, ['label' => 'date', 'required' => false])
            ->add('debiteur', DebiteurType::class, ['label' => 'Débiteur'])
            ->add('compteToUse', EntityType::class, ['label' => 'Compte à utiliser', 'class' => Compte::class])
            ->add('statut', ChoiceType::class, ['label' => 'statut', 'choices' => [
                Facture::PAYEE      => 'payée',
                Facture::ANNULEE    => 'annulée',
                Facture::OUVERTE    => 'ouverte'
            ]])
        ;

        RemarquesUtils::addRemarquesField($builder);

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => Facture::class
        ]);
    }
}
