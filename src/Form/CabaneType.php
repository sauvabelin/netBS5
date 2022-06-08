<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CabaneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, array('label' => 'Nom'))
            ->add('calendarId', TextType::class, array('label' => 'ID Calendrier Google'))
            ->add('location', TextType::class, array('label' => 'Localisation'))
            ->add('intendance', TextareaType::class, array('label' => 'Intendance'))
            ->add('demandeRecueText', TextareaType::class, array('label' => 'Texte demande réservation reçue'))
            ->add('demandeRefuseeText', TextareaType::class, array('label' => 'Texte demande réservation refusée'))
            ->add('demandeAnnuleeText', TextareaType::class, array('label' => 'Texte demande réservation annulée'))
            ->add('demandeModifieeText', TextareaType::class, array('label' => 'Texte demande réservation modifiée'))
            ->add('demandeAccepteeText', TextareaType::class, array('label' => 'Texte demande réservation acceptée'))
        ;
    }
}
