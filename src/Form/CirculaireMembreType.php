<?php

namespace App\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DateMaskType;
use NetBS\CoreBundle\Form\Type\MaskType;
use NetBS\CoreBundle\Form\Type\SexeType;
use NetBS\CoreBundle\Form\Type\TelephoneMaskType;
use NetBS\CoreBundle\Utils\Countries;
use NetBS\FichierBundle\Entity\Geniteur;
use NetBS\FichierBundle\Service\FichierConfig;
use App\Model\CirculaireMembre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CirculaireMembreType extends AbstractType
{
    private FichierConfig $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('familleId', HiddenType::class)
            ->add('numero', NumberType::class, array('label' => "Numéro BS", 'required' => false))
            ->add('prenom', TextType::class, array('label' => 'Prénom'))
            ->add('nom', TextType::class, array('label' => 'Nom de famille'))
            ->add('numeroAvs', MaskType::class, array('label' => 'Numéro AVS', 'mask' => "'mask' : '999.9999.9999.99'", 'required' => false))
            ->add('sexe', SexeType::class, array('label' => 'Sexe'))
            ->add('naissance', DateMaskType::class, array('label' => 'Date de naissance'))
            ->add('adresse', TextType::class, array('label' => "Adresse", 'required' => false))
            ->add('npa', NumberType::class, array('label' => "NPA", 'required' => false))
            ->add('inscription', DateMaskType::class, ['label' => 'Inscription'])
            ->add('localite', TextType::class, array('label' => 'Localité', 'required' => false))
            ->add('pays', ChoiceType::class, array(
                'label' => 'Pays',
                'required' => false,
                'choices' => array_flip(Countries::getCountries())
                ))
            ->add('email', EmailType::class, array('label' => 'Email', 'required' => false))
            ->add('telephone', TelephoneMaskType::class, array('label' => 'Téléphone', 'required' => false))
            ->add('natel', TelephoneMaskType::class, array('label' => 'Natel', 'required' => false))
            ->add('fonction', AjaxSelect2DocumentType::class, array(
                'label' => 'Fonction',
                'class' => $this->config->getFonctionClass(),
            ))
            ->add('groupe', AjaxSelect2DocumentType::class, array(
                'label' => 'Unité',
                'class' => $this->config->getGroupeClass(),
            ))
            ->add('r1statut', ChoiceType::class, [
                'label'     => 'Statut',
                'choices'   => array_flip(Geniteur::getStatutChoices())
            ])
            ->add('r1sexe', SexeType::class, array('label' => 'Sexe', 'required' => false))
            ->add('r1nom', TextType::class, array('label' => 'Nom', 'required' => false))
            ->add('r1prenom', TextType::class, array('label' => 'Prénom', 'required' => false))
            ->add('r1adresse', TextType::class, array('label' => 'Adresse', 'required' => false))
            ->add('r1npa', NumberType::class, array('label' => 'NPA', 'required' => false))
            ->add('r1localite', TextType::class, array('label' => 'Localité', 'required' => false))
            ->add('r1pays', ChoiceType::class, array(
                'label' => 'Pays',
                'required' => false,
                'choices' => array_flip(Countries::getCountries())
            ))
            ->add('r1telephone', TelephoneMaskType::class, array('label' => 'Téléphone', 'required' => false))
            ->add('r1email', EmailType::class, array('label' => 'Email', 'required' => false))
            ->add('r1profession', TextType::class, array('label' => 'Profession', 'required' => false))

            ->add('r2statut', ChoiceType::class, [
                'label'     => 'Statut',
                'choices'   => array_flip(Geniteur::getStatutChoices())
            ])
            ->add('r2sexe', SexeType::class, array('label' => 'Sexe', 'required' => false))
            ->add('r2nom', TextType::class, array('label' => 'Nom', 'required' => false))
            ->add('r2prenom', TextType::class, array('label' => 'Prénom', 'required' => false))
            ->add('r2adresse', TextType::class, array('label' => 'Adresse', 'required' => false))
            ->add('r2npa', NumberType::class, array('label' => 'NPA', 'required' => false))
            ->add('r2localite', TextType::class, array('label' => 'Localité', 'required' => false))
            ->add('r2pays', ChoiceType::class, array(
                'label' => 'Pays',
                'required' => false,
                'choices' => array_flip(Countries::getCountries())
            ))
            ->add('r2telephone', TelephoneMaskType::class, array('label' => 'Téléphone', 'required' => false))
            ->add('r2email', EmailType::class, array('label' => 'Email', 'required' => false))
            ->add('r2profession', TextType::class, array('label' => 'Profession', 'required' => false))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => CirculaireMembre::class
        ));
    }
}
