<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\FichierHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreUpdaterType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('naissance', DatepickerType::class, ['label' => "Date de naissance"])
            ->add('statut', ChoiceType::class, [
                'label' => "Statut",
                "choices"   => array_flip(FichierHelper::getStatutChoices($this->config->getMembreClass()))
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => $this->config->getMembreClass()
        ]);
    }
}
