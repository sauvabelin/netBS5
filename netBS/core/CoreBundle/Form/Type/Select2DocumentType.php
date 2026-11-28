<?php

namespace NetBS\CoreBundle\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class Select2DocumentType extends AbstractType
{

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-type']    = 'select2';
        $view->vars['data-ajax-class']      = $options['class'];
    }

    public function getParent(): ?string
    {
        return EntityType::class;
    }
}