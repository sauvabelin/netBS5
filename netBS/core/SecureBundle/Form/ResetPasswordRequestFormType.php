<?php

namespace NetBS\SecureBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('username', TextType::class, [
            'label' => "Nom d'utilisateur",
            'constraints' => [
                new NotBlank(message: "Veuillez saisir un nom d'utilisateur."),
                new Length(max: 180, maxMessage: "Le nom d'utilisateur est trop long."),
            ],
            'attr' => [
                'autocomplete' => 'username',
                'autofocus' => 'autofocus',
            ],
        ]);
    }
}
