<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Form;

use NetBS\AuthBundle\Dto\OidcClientDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

final class OidcClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                // The DTO property is a non-nullable string: an emptied field must
                // reach NotBlank, not crash the property accessor.
                'empty_data' => '',
                'constraints' => [new NotBlank()],
            ])
            ->add('scopes', TextType::class, [
                'label' => 'Scopes',
                'help'  => 'Séparés par des espaces, par ex. openid profile email',
                'empty_data' => '',
                'constraints' => [new NotBlank()],
            ])
            ->add('redirectUris', CollectionType::class, [
                'label' => 'URI de redirection',
                'entry_type' => UrlType::class,
                'entry_options' => ['constraints' => [new NotBlank(), new Url(['protocols' => ['http', 'https']])]],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'constraints' => [new Count(['min' => 1, 'minMessage' => 'Ajoutez au moins une URI de redirection.'])],
            ])
            ->add('postLogoutRedirectUris', CollectionType::class, [
                'label' => 'URI de redirection après déconnexion',
                'entry_type' => UrlType::class,
                'entry_options' => ['constraints' => [new NotBlank(), new Url(['protocols' => ['http', 'https']])]],
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('backchannelLogoutUri', UrlType::class, [
                'label' => 'URI de déconnexion (backchannel)',
                'required' => false,
                'constraints' => [new Url(['protocols' => ['http', 'https']])],
            ])
            ->add('logoUri', UrlType::class, [
                'label' => 'URL du logo',
                'help'  => 'Affiché sur la page de connexion netBS quand la connexion vient de ce client.',
                'required' => false,
                'default_protocol' => 'https',
                'constraints' => [new Url(['protocols' => ['http', 'https']])],
            ])
            ->add('requiredFlag', ChoiceType::class, [
                'label' => 'Option de compte requise',
                'help'  => 'Seuls les comptes avec cette option peuvent se connecter à ce client. Laissez vide pour autoriser tous les comptes actifs.',
                'choices' => array_combine(OidcClientDto::REQUIRED_FLAGS, OidcClientDto::REQUIRED_FLAGS),
                'required' => false,
                'placeholder' => '— aucune —',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OidcClientDto::class,
        ]);
    }
}
