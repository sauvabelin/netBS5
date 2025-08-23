<?php

namespace App\Form;

use App\Model\ReservationMessage;
use App\Model\SendInvoiceReservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SendInvoiceReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('montant', NumberType::class, ['label' => 'Montant', 'required' => true])
            ->add('autreFraisDescription', TextareaType::class, ['label' => 'Autre frais', 'required' => false])
            ->add('autreFraisMontant', NumberType::class, ['label' => 'Montant autre frais', 'required' => false])
            ->add('message', TextareaType::class, ['label' => "Message sur l'email", 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SendInvoiceReservation::class
        ]);
    }
}