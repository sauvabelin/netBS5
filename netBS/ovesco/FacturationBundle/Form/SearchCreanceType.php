<?php

namespace Ovesco\FacturationBundle\Form;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Form\Type\DaterangeType;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Form\Type\CreanceOuverteType;
use Ovesco\FacturationBundle\Model\SearchCreance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchCreanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('titre', TextType::class, ['label' => 'Titre de la créance', 'required' => false])
            ->add('remarques', TextType::class, ['label' => 'Remarques', 'required' => false])
            ->add('montant', NumberType::class, ['label' => 'Montant', 'required' => false])
            ->add('rabais', NumberType::class, ['label' => 'Rabais (%)', 'required' => false])
            ->add('date', DaterangeType::class, ['label' => 'Date de création', 'required' => false])
            ->add('isOuverte', CreanceOuverteType::class, ['label' => 'Créances ouvertes'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SearchCreance::class
        ]);
    }
}
