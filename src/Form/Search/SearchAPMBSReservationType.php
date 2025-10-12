<?php

namespace App\Form\Search;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Model\SearchAPMBSReservation;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchAPMBSReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
             ->add('cabane', AjaxSelect2DocumentType::class, array(
                'label'     => 'Cabane',
                'class'     => Cabane::class,
                'required'  => false
            ))
            ->add('status', ChoiceType::class, [
                "label" => "Statut", 
                "required" => false,
                "choices" => [
                    "En attente" => APMBSReservation::PENDING,
                    "Accepté"    => APMBSReservation::ACCEPTED,
                    "Refusé"     => APMBSReservation::REFUSED,
                    "Annulé"     => APMBSReservation::CANCELLED,
                    "Modification en attente" => APMBSReservation::MODIFICATION_PENDING,
                    "Modification acceptée" => APMBSReservation::MODIFICATION_ACCEPTED,
                    "Facture envoyée" => APMBSReservation::INVOICE_SENT,
                    "Terminée" => APMBSReservation::CLOSED
                ],
            ])
            ->add('start', DatepickerType::class, array(
                'label'     => 'Date de début',
                'required'  => false
            ))
            ->add('end', DatepickerType::class, array(
                'label'     => 'Date de fin',
                'required'  => false
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => SearchAPMBSReservation::class
        ));
    }
}
