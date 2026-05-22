<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

final class AuditPickType extends AbstractType
{
    public function __construct(
        private readonly SecureConfig $secureConfig,
        private readonly FichierConfig $fichierConfig,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', AjaxSelect2DocumentType::class, [
                'class'    => $this->secureConfig->getUserClass(),
                'label'    => 'Utilisateur',
                'required' => false,
            ])
            ->add('groupe', AjaxSelect2DocumentType::class, [
                'class'    => $this->fichierConfig->getGroupeClass(),
                'label'    => 'Groupe',
                'required' => false,
            ]);
    }
}
