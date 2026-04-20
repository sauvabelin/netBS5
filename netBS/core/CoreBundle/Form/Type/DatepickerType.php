<?php

namespace NetBS\CoreBundle\Form\Type;

use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
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
                $date = \DateTime::createFromFormat($phpFormat, $string);
                if ($date === false) {
                    throw new TransformationFailedException(
                        sprintf('La date "%s" ne correspond pas au format attendu "%s".', $string, $phpFormat)
                    );
                }
                return $date;
            }
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $phpFormat = $this->params->getValue('format', 'php_date');
        $flatpickrFormat = $this->phpToFlatpickrFormat($phpFormat);

        $view->vars['attr']['data-controller'] = 'flatpickr';
        $view->vars['attr']['data-flatpickr-format-value'] = $flatpickrFormat;
        $view->vars['attr']['placeholder'] = $this->params->getValue('format', 'js_date');
    }

    /**
     * Convert PHP date format tokens to Flatpickr format tokens.
     * Most tokens are identical; only the diverging ones are mapped.
     */
    private function phpToFlatpickrFormat(string $phpFormat): string
    {
        $map = [
            'G' => 'H',  // PHP: 0-23 no pad → Flatpickr: H
            'g' => 'G',  // PHP: 1-12 no pad → Flatpickr: G
            'A' => 'K',  // PHP: AM/PM → Flatpickr: K
            'a' => 'K',  // PHP: am/pm → Flatpickr: K
        ];

        $result = '';
        $escaped = false;
        for ($i = 0; $i < strlen($phpFormat); $i++) {
            $c = $phpFormat[$i];
            if ($c === '\\') {
                $escaped = true;
                continue;
            }
            if ($escaped) {
                $result .= '\\' . $c;
                $escaped = false;
                continue;
            }
            $result .= $map[$c] ?? $c;
        }

        return $result;
    }

    public function getParent()
    {
        return TextType::class;
    }
}
