<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Form;

use NetBS\AuthBundle\Dto\OidcClientDto;
use NetBS\AuthBundle\Service\OidcEndpoints;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

final class OidcClientType extends AbstractType
{
    public function __construct(private readonly OidcEndpoints $endpoints)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $catalogue = $this->endpoints->claimCatalogue();
        $claimChoices = array_combine($catalogue, $catalogue);

        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'constraints' => [new NotBlank()],
            ])
            ->add('scopes', TextType::class, [
                'label' => 'Scopes',
                'help'  => 'Space-separated list, e.g. openid profile email groups',
                'constraints' => [new NotBlank()],
            ])
            ->add('redirectUris', CollectionType::class, [
                'label' => 'Redirect URIs',
                'entry_type' => UrlType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'constraints' => [new Count(['min' => 1, 'minMessage' => 'At least one redirect URI is required'])],
            ])
            ->add('postLogoutRedirectUris', CollectionType::class, [
                'label' => 'Post-logout redirect URIs',
                'entry_type' => UrlType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'required' => false,
                'prototype' => true,
                'by_reference' => false,
            ])
            ->add('backchannelLogoutUri', UrlType::class, [
                'label' => 'Backchannel logout URI',
                'required' => false,
            ])
            ->add('logoUri', UrlType::class, [
                'label' => 'Logo URL',
                'help'  => 'Shown on the netBS login page when this client initiates a sign-in.',
                'required' => false,
            ])
            ->add('allowedClaims', ChoiceType::class, [
                'label' => 'Allowed claims',
                'choices' => $claimChoices,
                'multiple' => true,
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OidcClientDto::class,
        ]);
    }
}
