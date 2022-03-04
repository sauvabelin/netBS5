<?php

namespace NetBS\CoreBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class DynamicListShareType extends AbstractType
{
    protected $secureConfig;

    public function __construct(SecureConfig $config)
    {
        $this->secureConfig = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('shares', AjaxSelect2DocumentType::class, [
                'label' => 'Partager la liste',
                'multiple' => true,
                'class' => $this->secureConfig->getUserClass(),
            ])
        ;
    }
}
