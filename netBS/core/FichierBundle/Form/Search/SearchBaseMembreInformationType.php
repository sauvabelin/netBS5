<?php

namespace NetBS\FichierBundle\Form\Search;

use NetBS\CoreBundle\Form\Type\DaterangeType;
use NetBS\CoreBundle\Form\Type\SexeType;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\Search\SearchBaseMembreInformation;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\FichierHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchBaseMembreInformationType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('prenom', TextType::class, array('label' => 'Prénom', 'required' => false))
            ->add('nom', TextType::class, array('label' => 'Nom', 'required' => false))
            ->add('naissance', DaterangeType::class, array('required' => false,
                'gt_options'    => ['label' => "Né après le"],
                'lt_options'    => ['label' => "Né avant le"]
            ))
            ->add('sexe', SexeType::class, array('label' => 'Sexe', 'required' => false))
            ->add('statut', ChoiceType::class, array(
                'label'         => 'Statut',
                'choices'       => FichierHelper::getStatutChoices($this->config->getMembreClass(), true),
                'data'          => BaseMembre::INSCRIT,
                'required'      => false
            ))
            ->add('inscription', DaterangeType::class, array('required' => false,
                'gt_options'    => ['label' => "Inscrit après le"],
                'lt_options'    => ['label' => "Inscrit avant le"]
            ))
            ->add('desinscription', DaterangeType::class, array('required' => false,
                'gt_options'    => ['label' => "Désinscrit après le"],
                'lt_options'    => ['label' => "Désinscrit avant le"]
            ))
            ->add('attributions', SearchAttributionType::class)
            ->add('obtentionsDistinction', SearchObtentionDistinctionType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => SearchBaseMembreInformation::class
        ));
    }
}
