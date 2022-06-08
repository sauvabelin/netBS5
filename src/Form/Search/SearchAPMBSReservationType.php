<?php

namespace App\Form\Search;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Model\SearchReservation;
use NetBS\CoreBundle\Form\Type\DaterangeType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchAPMBSReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('prenom', TextType::class, array('label' => 'Prénom', 'required' => false))
            ->add('nom', TextType::class, array('label' => 'Nom', 'required' => false))
            ->add('email', TextType::class, array('label' => 'E-mail', 'required' => false))
            ->add('phone', TextType::class, array('label' => 'Téléphone', 'required' => false))
            ->add('unite', TextType::class, array('label' => 'Unité/Groupe', 'required' => false))
            ->add('status', ChoiceType::class, array(
                'label'         => 'Status',
                'choices'       => [
                    'Acceptée' => APMBSReservation::ACCEPTED,
                    'Refusée' => APMBSReservation::REFUSED,
                    'En Attente' => APMBSReservation::PENDING,
                ],
                'required'      => false
            ))
            ->add('cabane', EntityType::class, [
                'label' => 'Cabane',
                'class' => Cabane::class,
                'choice_label' => 'nom',
            ])
            ->add('start', DaterangeType::class, array('required' => false,
                'gt_options'    => ['label' => "Début après le"],
                'lt_options'    => ['label' => "Début avant le"]
            ))
            ->add('end', DaterangeType::class, array('required' => false,
                'gt_options'    => ['label' => "Fin après le"],
                'lt_options'    => ['label' => "Fin avant le"]
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => SearchReservation::class
        ));
    }
}
