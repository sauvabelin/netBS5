<?php

namespace App\Form;

use NetBS\CoreBundle\Form\Type\DateMaskType;
use NetBS\CoreBundle\Form\Type\MaskType;
use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use NetBS\CoreBundle\Form\Type\SexeType;
use NetBS\CoreBundle\Form\Type\TelephoneMaskType;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\Groupe;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Model\Inscription;

class InscriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('familleId', HiddenType::class)
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('sexe', SexeType::class, ['label' => 'Sexe'])
            ->add('naissance', DateMaskType::class, ['label' => 'Date de naissance'])
            ->add('numeroAvs', MaskType::class, ['label' => 'Numéro AVS', 'required' => false, 'mask' => "'mask' : '999.9999.9999.99'"])
            ->add('adresse', TextType::class, ['label' => 'Adresse', 'required' => false])
            ->add('npa', TextType::class, ['label' => 'NPA', 'required' => false])
            ->add('localite', TextType::class, ['label' => 'Localité', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
            ->add('telephone', TelephoneMaskType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('professionsParents', TextType::class, ['label' => 'Professions des parents (séparer par une virgule)', 'required' => false])
            ->add('unite', Select2DocumentType::class, array(
                'class' => Groupe::class,
                'label' => 'Unité'
            ))
            ->add('fonction', Select2DocumentType::class, array(
                'class' => Fonction::class,
                'label' => 'Fonction'
            ));

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Inscription::class
        ]);
    }
}
