<?php

namespace App\Form;

use App\Entity\Cabane;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CabaneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('calendarId', TextType::class, ['label' => 'ID du calendrier google'])
            ->add('googleFormUrl', TextType::class, ['label' => 'URL du formulaire google'])
            ->add('fromEmail', TextType::class, ['label' => 'Email d\'envoi'])
            ->add('latitude', NumberType::class, ['label' => 'Latitude'])
            ->add('longitude', NumberType::class, ['label' => 'Longitude'])
            ->add('availabilityRule', TextareaType::class, ['label' => 'Règle de disponibilité'])
            ->add('intendants', AjaxSelect2DocumentType::class, [
                'label'     => 'Intendants',
                'class'     => 'App\Entity\Intendant',
                'multiple'  => true
            ])
            ->add('timePeriods', AjaxSelect2DocumentType::class, [
                'label'     => 'Périodes de journée',
                'class'     => 'App\Entity\CabaneTimePeriod',
                'multiple'  => true
            ])

            ->add('receivedEmail', TextareaType::class, ['label' => 'Emails de réception', 'required' => false])
            ->add('rejectedEmail', TextareaType::class, ['label' => 'Emails de refus', 'required' => false])
            ->add('correctionEmail', TextareaType::class, ['label' => 'Emails de correction', 'required' => false])
            ->add('confirmedEmail', TextareaType::class, ['label' => 'Emails de confirmation', 'required' => false])
            ->add('cancelledEmail', TextareaType::class, ['label' => 'Emails d\'annulation', 'required' => false])
            ->add('prices', TextareaType::class, ['label' => 'Prix', 'required' => false])
            ->add('disabledDates', TextareaType::class, ['label' => 'Dates désactivées', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Cabane::class
        ]);
    }
}