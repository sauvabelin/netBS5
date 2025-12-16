<?php

namespace Iacopo\MailingBundle\Form;

use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
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

        // Add event listener to validate that only the correct field is filled
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $type = $data['type'] ?? null;

            if (!$type) {
                return;
            }

            // Clear fields that don't match the selected type
            if ($type !== MailingTarget::TYPE_EMAIL) {
                $data['targetEmail'] = null;
            }
            if ($type !== MailingTarget::TYPE_USER) {
                $data['targetUser'] = null;
            }
            if ($type !== MailingTarget::TYPE_UNITE) {
                $data['targetGroup'] = null;
            }
            if ($type !== MailingTarget::TYPE_ROLE) {
                $data['targetFonction'] = null;
            }
            if ($type !== MailingTarget::TYPE_LIST) {
                $data['targetList'] = null;
            }

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $target = $event->getData();

            if (!$target instanceof MailingTarget) {
                return;
            }

            $type = $target->getType();

            // Validate that the correct field has a value
            $hasValue = false;
            $fieldName = '';

            switch ($type) {
                case MailingTarget::TYPE_EMAIL:
                    $hasValue = !empty($target->getTargetEmail());
                    $fieldName = 'targetEmail';
                    break;
                case MailingTarget::TYPE_USER:
                    $hasValue = $target->getTargetUser() !== null;
                    $fieldName = 'targetUser';
                    break;
                case MailingTarget::TYPE_UNITE:
                    $hasValue = $target->getTargetGroup() !== null;
                    $fieldName = 'targetGroup';
                    break;
                case MailingTarget::TYPE_ROLE:
                    $hasValue = $target->getTargetFonction() !== null;
                    $fieldName = 'targetFonction';
                    break;
                case MailingTarget::TYPE_LIST:
                    $hasValue = $target->getTargetList() !== null;
                    $fieldName = 'targetList';
                    break;
            }

            if (!$hasValue && $fieldName) {
                $form->get($fieldName)->addError(new FormError('Ce champ est requis pour le type sélectionné.'));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MailingTarget::class
        ]);
    }
}
