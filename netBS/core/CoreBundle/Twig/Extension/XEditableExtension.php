<?php

namespace NetBS\CoreBundle\Twig\Extension;

use Doctrine\Common\Util\ClassUtils;
use NetBS\CoreBundle\Validator\Constraints\UserValidator;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class XEditableExtension extends AbstractExtension
{
    /**
     * @var UserValidator
     */
    protected $validator;

    /**
     * @var FormFactoryInterface
     */
    protected $form;

    /**
     * @var Environment
     */
    protected $twig;

    public function __construct(FormFactoryInterface $factory, Environment $twig, UserValidator $validator)
    {
        $this->form = $factory;
        $this->twig = $twig;
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'xeditable';
    }

    public function getFunctions() {

        return [
            new TwigFunction('xeditable', array($this, 'toXeditable')),
            new TwigFunction('get_class', array($this, 'getClass')),
            new TwigFunction('editProperty', [$this, 'editProperty'])
        ];
    }

    public function getFilters() {

        return [
            new TwigFilter('xEditableChoices', array($this, 'toChoices'), array('is_safe' => array('html'))),
            new TwigFilter('xEditableSelect2', array($this, 'toSelect2'), array('is_safe' => array('html')))
        ];
    }

    /**
     * @param $item
     * @param $property
     * @return bool
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function editProperty($item, $property) {

        return $this->validator->canUpdate($item, $property);
    }

    public function toXeditable($object, $field, $type, $typeParams = []) {

        $form = $this->form->createBuilder(FormType::class, $object, array('csrf_protection' => false));
        $form->add($field, $type, $typeParams);
        $view = $form->getForm()->createView();
        return $this->twig->render('@NetBSCore/column/xeditable.column.twig', array(
            'form'  => $view
        ));
    }

    /**
     * @param FormView $object
     * @return string
     */
    public function getClass($object) {

        return ClassUtils::getRealClass(get_class($object));
    }

    public function toChoices(array $choices) {

        $return = [];

        /** @var ChoiceView $option */
        foreach($choices as $option)
            $return[] = (object)['value' => $option->value, 'text' => $option->label];

        return $return;
    }

    public function toSelect2(array $choices) {

        $return = [];

        /** @var ChoiceView $option */
        foreach($choices as $option)
            $return[] = (object)['id' => $option->value, 'text' => $option->label];

        return $return;
    }

}
