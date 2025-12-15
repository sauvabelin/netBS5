<?php

namespace Iacopo\MailingBundle\Form;

use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MailingTargetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type de destinataire',
                'choices' => [
                    'Adresse email' => MailingTarget::TYPE_EMAIL,
                    'Utilisateur NetBS' => MailingTarget::TYPE_USER,
                    'Unité' => MailingTarget::TYPE_UNITE,
                    'Rôle' => MailingTarget::TYPE_ROLE,
                    'Liste' => MailingTarget::TYPE_LIST,
                ],
                'required' => true,
                'attr' => ['class' => 'target-type-selector']
            ])
            ->add('targetEmail', EmailType::class, [
                'label' => 'Adresse email',
                'required' => false,
                'attr' => ['class' => 'target-email-field']
            ])
            ->add('targetUser', AjaxSelect2DocumentType::class, [
                'label' => 'Utilisateur',
                'class' => 'App\Entity\BSUser',
                'required' => false,
                'attr' => ['class' => 'target-user-field']
            ])
            ->add('targetGroup', AjaxSelect2DocumentType::class, [
                'label' => 'Unité',
                'class' => 'App\Entity\BSGroupe',
                'required' => false,
                'attr' => ['class' => 'target-group-field']
            ])
            ->add('targetFonction', AjaxSelect2DocumentType::class, [
                'label' => 'Rôle',
                'class' => 'NetBS\FichierBundle\Entity\Fonction',
                'required' => false,
                'attr' => ['class' => 'target-fonction-field']
            ])
            ->add('targetList', AjaxSelect2DocumentType::class, [
                'label' => 'Liste',
                'class' => 'Iacopo\MailingBundle\Entity\MailingList',
                'required' => false,
                'attr' => ['class' => 'target-list-field']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MailingTarget::class
        ]);
    }
}
