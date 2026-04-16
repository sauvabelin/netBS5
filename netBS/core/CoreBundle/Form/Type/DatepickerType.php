<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DatepickerType extends AbstractType
{
    protected $params;

    public function __construct(ParameterManager $parameterManager)
    {
        $this->params = $parameterManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $phpFormat = $this->params->getValue('format', 'php_date');

        $builder->addViewTransformer(new CallbackTransformer(
            function ($date) use ($phpFormat) {
                if ($date instanceof \DateTime) {
                    return $date->format($phpFormat);
                }
                return null;
            },
            function ($string) use ($phpFormat) {
                if ($string === '' || $string === null) {
                    return null;
                }
                return \DateTime::createFromFormat($phpFormat, $string) ?: null;
            }
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $mask = $this->params->getValue('format', 'php_date');
        $mask = preg_replace('/[a-zA-Z]/', '9', (new \DateTime())->format($mask));

        $view->vars['attr']['data-controller'] = 'input-mask';
        $view->vars['attr']['data-input-mask-pattern-value'] = "'mask' : '$mask'";
        $view->vars['attr']['placeholder'] = $this->params->getValue('format', 'js_date');
    }

    public function getParent()
    {
        return TextType::class;
    }
}
