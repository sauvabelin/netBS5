<?php

namespace NetBS\FichierBundle\Form\Personne;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\FichierBundle\Utils\FichierHelper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreType extends PersonneType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('naissance', DatepickerType::class, array('label' => 'Date de naissance'))
            ->add('inscription', DatepickerType::class, array('label' => 'Date d\'inscription'))
            ->add('numeroAvs', TextType::class, array('label' => 'Numéro AVS'))
            ->add('desinscription', DatepickerType::class, array('label' => 'Date de désinscription'))
            ->add('statut', ChoiceType::class, array(
                'label'     => 'Statut',
                'choices'   => FichierHelper::getStatutChoices($this->config->getMembreClass(), true)
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getMembreClass()
        ));
    }
}
