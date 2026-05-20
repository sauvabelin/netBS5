<?php

declare(strict_types=1);

namespace App\Form\Extension;

use NetBS\CoreBundle\Form\FormResponseAttributes;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marks the current request when any root form is submitted-and-invalid.
 * Paired with InvalidFormStatusListener (bumps the response to 422 so Turbo
 * natively re-renders the form instead of discarding the 200 reply).
 */
final class InvalidFormStatusExtension extends AbstractTypeExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->isRoot() || $form->isValid()) {
                return;
            }
            $request = $this->requestStack->getCurrentRequest();
            if ($request === null) {
                return;
            }
            $request->attributes->set(FormResponseAttributes::ROOT_INVALID, true);
        }, /* priority */ -100);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
