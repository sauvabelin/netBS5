<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Service\NetBS;
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
        $this->params   = $parameterManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'format'  => $this->params->getValue('format', 'js_date')
        ));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new CallbackTransformer(

                function($date) {
                    if($date instanceof \DateTime)
                        return $date->format($this->params->getValue('format', 'php_date'));
                },
                function($string) {
                    if($string === '' || is_null($string))
                        return null;

                    return \DateTime::createFromFormat(($this->params->getValue('format', 'php_date')), $string);
                }
            )
        );
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['attr']['data-type']        = 'datetimepicker';
        $view->vars['attr']['data-date-format'] = $options['format'];
        $view->vars['attr']['data-min-view']    = 2;
        $view->vars['attr']['placeholder']      = $options['format'];
    }

    public function getParent()
    {
        return TextType::class;
    }
}