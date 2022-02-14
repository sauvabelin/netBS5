<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupeTypeType extends AbstractType
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
            ->add('affichageEffectifs', SwitchType::class, array('label' => "Affichage des effectifs"))
            ->add('groupeCategorie', Select2DocumentType::class, array(
                'label' => 'Catégorie d\'unité',
                'class' => $this->config->getGroupeCategorieClass()
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getGroupeTypeClass()
        ));
    }
}
