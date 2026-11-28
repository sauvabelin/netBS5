<?php

namespace App\Form\Search;

use App\Model\SearchMembre;
use Symfony\Component\Form\FormBuilderInterface;
use NetBS\FichierBundle\Form\Search\SearchBaseMembreInformationType as base;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchBaseMembreInformationType extends base
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('noAdabs', SearchNoAdabsType::class, ["label" => "Pas à l'ADABS", 'data' => true])
            ->add('noApmbs', SearchNoAPMBSType::class, ["label" => "Pas à l'APMBS", 'data' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'data_class' => SearchMembre::class
        ));
    }
}
