<?php

namespace NetBS\FichierBundle\Form\Contact;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\FichierBundle\Entity\Email;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType as SE;

class BSEmailType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', SE::class, array('label' => 'Adresse e-mail'))
            ->add('expediable', SwitchType::class, array('label' => 'Prioritaire', 'required' => false))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getEmailClass()
        ));
    }
}
