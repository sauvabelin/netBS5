<?php

namespace NetBS\FichierBundle\Form;

use NetBS\FichierBundle\Form\Contact\ContactInformationType;
use NetBS\FichierBundle\Form\Personne\GeniteurType;
use NetBS\FichierBundle\Utils\FichierHelper;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FamilleType extends ContactInformationType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('nom', TextType::class, array('label' => 'Nom de famille'))
            ->add('validity', ChoiceType::class, array(
                'label'     => 'Validité',
                'choices'   => FichierHelper::getValidityChoices($this->config->getFamilleClass(), true)
            ))
            ->add('geniteurs', CollectionType::class, array(
                'entry_type'    => GeniteurType::class,
                'allow_delete'  => true,
                'allow_add'     => true
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getFamilleClass(),
        ));
    }
}
