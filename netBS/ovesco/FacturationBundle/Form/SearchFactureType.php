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
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFactureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('factureId', FactureIdType::class, ['label' => 'Numéro de facture'])
            // ->add('date', DatepickerType::class, ['label' => 'Date de création', 'required' => false])
            ->add('date', DaterangeType::class, [
                'label' => 'date de création'
            ])
            ->add('remarques', TextType::class, ['label' => 'Remarques', 'required' => false])
            ->add('dateImpression', LatestDateType::class, ['label' => "Date de dernière impression", 'property' => 'impression'])
            ->add('datePaiement', LatestDateType::class, ['label' => 'Date de dernier paiement', 'property' => 'paiement'])
            ->add('creanceInFacture', CreanceInFactureType::class, ['label' => 'Contient une créance nommée'])
            ->add('isPrinted', HasBeenPrintedType::class, ['label' => "En attente d'impression"])
            ->add('compteToUse', EntityType::class, ['label' => 'Compte', 'required' => false, 'class' => Compte::class])
            ->add('statut', ChoiceType::class, ['label' => 'statut', 'required' => false, 'choices' => array_flip([
                Facture::PAYEE      => 'payée',
                Facture::ANNULEE    => 'annulée',
                Facture::OUVERTE    => 'ouverte'
            ])])
            ->add('nombreDeRappels', CountSearchType::class, ['label' => 'Nombre de rappels', 'property' => 'rappels'])
            ->add('nombreDeCreances', CountSearchType::class, ['label' => 'Nombre de créances', 'property' => 'creances'])
            ->add('montant', CompareType::class, ['label' => 'Montant total', 'property' => 'montant', 'function' => 'floatval'])
            ->add('montantPaye', CompareType::class, ['label' => 'Montant payé', 'property' => 'montantPaye', 'function' => 'floatval'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => SearchFacture::class
        ]);
    }
}
