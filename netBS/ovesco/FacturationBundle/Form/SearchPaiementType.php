<?php

namespace Ovesco\FacturationBundle\Form;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Form\Type\DaterangeType;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\Type\CountSearchType;
use Ovesco\FacturationBundle\Form\Type\CreanceInFactureType;
use Ovesco\FacturationBundle\Form\Type\FactureIdType;
use Ovesco\FacturationBundle\Form\Type\HasBeenPrintedType;
use Ovesco\FacturationBundle\Form\Type\LatestDateType;
use Ovesco\FacturationBundle\Form\Type\CompareType;
use Ovesco\FacturationBundle\Model\SearchFacture;
use Ovesco\FacturationBundle\Model\SearchPaiement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchPaiementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('factureId', FactureIdType::class, ['label' => 'Numéro de facture'])
            ->add('date', DaterangeType::class, [
                'label' => 'date de paiement'
            ])
            ->add('compte', EntityType::class, ['label' => 'Compte', 'required' => false, 'class' => Compte::class])
            ->add('montant', CompareType::class, ['label' => 'Montant total', 'property' => 'montant', 'function' => 'floatval'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => SearchPaiement::class
        ]);
    }
}
