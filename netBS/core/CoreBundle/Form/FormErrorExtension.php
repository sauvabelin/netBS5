<?php

namespace NetBS\CoreBundle\Form;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FormErrorExtension extends AbstractTypeExtension
{
    private $session;

    public function __construct(SessionInterface $session)
    {
        $this->session  = $session;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            if($event->getForm()->getErrors(true)->count() > 0)
                $this->session->getFlashBag()->add('error',
                    "Une erreur s'est produite dans un formulaire, veuillez vérifier les données saisies");
        });
    }

    /**
     * Returns the name of the type being extended.
     */
    public static function getExtendedTypes(): iterable {
        return [
            FormType::class
        ];
    }
}
