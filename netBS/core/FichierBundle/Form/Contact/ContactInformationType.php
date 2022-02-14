<?php

namespace NetBS\FichierBundle\Form\Contact;

use NetBS\FichierBundle\Entity\ContactInformation;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactInformationType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('adresses', CollectionType::class, array(
                'label'         => 'Adresses postales',
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => AdresseType::class,
                'prototype'     => true
            ))
            ->add('emails', CollectionType::class, array(
                'label'         => 'Adresses e-mail',
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => BSEmailType::class,
                'prototype'     => true
            ))
            ->add('telephones', CollectionType::class, array(
                'label'         => 'Numéros de téléphone',
                'allow_add'     => true,
                'allow_delete'  => true,
                'entry_type'    => TelephoneType::class,
                'prototype'     => true
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => $this->config->getContactInformationClass()
        ]);
    }
}
