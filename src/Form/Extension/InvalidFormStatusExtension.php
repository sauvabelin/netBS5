<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Marks the current request when any root form is submitted-and-invalid.
 *
 * Paired with {@see \App\EventListener\InvalidFormStatusListener}: that listener
 * reads the mark on kernel.response and bumps a 200 HTML reply to 422. The pair
 * lets Turbo natively handle form re-renders (Turbo respects 4xx but discards
 * non-redirect 200s on form submissions) so every form across the app gets
 * correct re-render behaviour without controller or template changes.
 *
 * Applies to every form because it extends FormType, the root of the type
 * hierarchy.
 */
final class InvalidFormStatusExtension extends AbstractTypeExtension
{
    public const REQUEST_ATTRIBUTE = '_form_root_invalid';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            if (!$form->isRoot()) {
                return;
            }
            if ($form->isValid()) {
                return;
            }
            $request = $this->requestStack->getCurrentRequest();
            if ($request === null) {
                return;
            }
            $request->attributes->set(self::REQUEST_ATTRIBUTE, true);
        }, /* priority */ -100);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
