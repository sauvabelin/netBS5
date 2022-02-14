<?php

namespace NetBS\FichierBundle\Form\Personne;

use NetBS\FichierBundle\Utils\FichierHelper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeniteurType extends PersonneType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('nom', TextType::class, array(
                'label'     => "Nom (Si différent du nom de famille)",
                'required'  => false
            ))
            ->add('profession', TextType::class, array(
                'label'     => 'Profession',
                'required'  => false
            ))
            ->add('statut', ChoiceType::class, array(
                'label'     => 'Statut',
                'choices'   => FichierHelper::getStatutChoices($this->config->getGeniteurClass(), true)
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', $this->config->getGeniteurClass());
    }
}
