<?php

namespace NetBS\FichierBundle\Form\Contact;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\Utils\Countries;
use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdresseType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('rue', TextType::class, array('label' => 'Rue', 'required' => false))
            ->add('npa', NumberType::class, array('label' => 'NPA', 'required' => false))
            ->add('localite', TextType::class, array('label' => 'Localité', 'required' => false))
            ->add('expediable', SwitchType::class, array('label' => 'Prioritaire', 'required' => false))
            ->add('pays', ChoiceType::class, array(
                'label' => 'Pays',
                'required' => false,
                'choices' => array_flip(Countries::getCountries()),
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getAdresseClass()
        ));
    }
}
