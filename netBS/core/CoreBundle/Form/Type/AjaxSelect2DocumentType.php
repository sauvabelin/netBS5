<?php

namespace NetBS\CoreBundle\Form\Type;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\CoreBundle\Select2\Select2ProviderManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AjaxSelect2DocumentType extends AbstractType
{
    protected $manager;

    protected $providerManager;

    public function __construct(EntityManagerInterface $manager, Select2ProviderManager $providerManager)
    {
        $this->manager          = $manager;
        $this->providerManager  = $providerManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'multiple'      => false,
            'null_option'   => false
        ));

        $resolver->setRequired('class');
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addViewTransformer(new CallbackTransformer(

            function ($objet) {

                if(is_array($objet) || $objet instanceof Collection) {
                    return implode(',', array_map(function($item) {
                        return $item->getId();
                    }, $objet));
                }

                if(is_object($objet) && method_exists($objet, 'getId'))
                    return $objet->getId();

                return null;

            }, function ($ids) use ($options) {

                $class = $options['class'];

                if(is_string($class) && $class != '')
                    $class  = [$class];

                if(is_array($class)) {

                    $resultSet = [];
                    $ids       = is_array($ids) ? $ids : explode(',', $ids);

                    foreach($ids as $id) {
                        foreach ($class as $className) {
                            $objet = $this->manager->find($className, $id);
                            if (!is_null($objet))
                                $resultSet[] = $objet;

                        }
                    }

                    return $options['multiple'] ? $resultSet : array_shift($resultSet);
                }

                return null;
            }
        ));

    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $provider   = $this->providerManager->getProvider($options['class']);
        $choices    = [];

        $view->vars['attr']['data-type']        = 'ajax-select2';
        $view->vars['attr']['data-ajax-class']  = base64_encode($options['class']);
        $view->vars['attr']['data-null-option'] = $options['null_option'] ? '1' : '0';
        $view->vars['multiple']                 = $options['multiple'];

        if ($options['multiple'])
            $view->vars['full_name'] .= '[]';

        if($form->getData() !== null) {

            if (!$options['multiple'])
                $choices[] = $this->itemToChoiceView($form->getData(), $provider);

            else
                foreach ($form->getData() as $item)
                    $choices[] = $this->itemToChoiceView($item, $provider);
        }

        $view->vars['base_choices'] = $choices;
    }

    protected function itemToChoiceView($item, Select2ProviderInterface $provider) {

        return new ChoiceView(
            $item,
            $item->getId(),
            $provider->toString($item)
        );
    }

    public function getParent()
    {
        return TextType::class;
    }
}
