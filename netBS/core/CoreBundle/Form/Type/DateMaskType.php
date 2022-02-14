<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateMaskType extends MaskType
{
    private $params;

    public function __construct(ParameterManager $params)
    {
        $this->params   = $params;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $format = (new \DateTime())->format($this->params->getValue('format', 'php_date'));
        $format = preg_replace('/[0-9]/', '9', $format);
        $resolver->setDefault('mask', "'mask' : '$format'");
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
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

    public function getParent()
    {
        return MaskType::class;
    }
}