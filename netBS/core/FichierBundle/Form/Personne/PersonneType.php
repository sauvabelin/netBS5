<?php

namespace NetBS\FichierBundle\Form\Personne;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\FichierBundle\Form\Contact\ContactInformationType;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class PersonneType extends ContactInformationType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('prenom', TextType::class, array('label' => 'prénom'))
            ->add('famille', AjaxSelect2DocumentType::class, [
                'label'     => 'famille',
                'class'     => $this->config->getFamilleClass()
            ])
            ->add('sexe', ChoiceType::class, array(
                'label'     => 'sexe',
                'choices'   => [
                    'Homme' => Personne::HOMME,
                    'Femme' => Personne::FEMME
                ]
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }
}
