<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\FichierHelper;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupeType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, array('label' => 'Nom'))
            ->add('parent', AjaxSelect2DocumentType::class, array(
                'class'         => $this->config->getGroupeClass(),
                'label'         => 'Groupe parent'
            ))
            ->add('groupeType', AjaxSelect2DocumentType::class, array(
                'class'         => $this->config->getGroupeTypeClass(),
                'label'         => 'Type de groupe',
            ))
            ->add('validity', ChoiceType::class, array(
                'label'         => 'Statut',
                'choices'       => FichierHelper::getValidityChoices($this->config->getGroupeClass(), true)
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => $this->config->getGroupeClass()
        ]);
    }
}
