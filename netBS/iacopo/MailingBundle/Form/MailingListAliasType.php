<?php

namespace Iacopo\MailingBundle\Form;

use Iacopo\MailingBundle\Entity\MailingListAlias;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailingListAliasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('address', TextType::class, [
                'label' => 'Adresse email alternative',
                'required' => true,
                'attr' => ['placeholder' => 'ex: info@sauvabelin.ch'],
                'constraints' => [
                    new NotBlank(['message' => 'L\'adresse ne peut pas être vide']),
                    new Email(['message' => 'L\'adresse email n\'est pas valide'])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MailingListAlias::class
        ]);
    }
}
