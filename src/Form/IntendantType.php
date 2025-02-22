<?php

namespace App\Form;

use App\Entity\Intendant;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntendantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('email', TextType::class, ['label' => 'Email'])
            ->add('phone', TextType::class, ['label' => 'Téléphone'])
            ->add('user', AjaxSelect2DocumentType::class, [
                'label'     => 'Utilisateur',
                'class'     => 'App\Entity\BSUser',
                'multiple'  => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Intendant::class
        ]);
    }
}