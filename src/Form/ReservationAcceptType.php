<?php

namespace App\Form;

use App\Model\AcceptReservation;
use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationAcceptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            
            ->add('intendantDebut', Select2DocumentType::class, [
                'label'     => 'Intendant ouverture',
                'class'     => 'App\Entity\Intendant',
                'multiple'  => false,
                'choices'   => $options['cabane']->getIntendants(),
            ])
            ->add('intendantFin', Select2DocumentType::class, [
                'label'     => 'Intendant fermeture',
                'class'     => 'App\Entity\Intendant',
                'multiple'  => false,
                'choices'   => $options['cabane']->getIntendants(),
            ])
            ->add('message', TextareaType::class, ['label' => 'Message'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('cabane');
        
        $resolver->setDefaults([
            'data_class' => AcceptReservation::class,
        ]);
    }
}