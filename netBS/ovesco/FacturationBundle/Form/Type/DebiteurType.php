<?php

namespace Ovesco\FacturationBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;
use Ovesco\FacturationBundle\Select2\Select2DebiteurProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DebiteurType extends AbstractType
{
    protected $manager;

    protected $provider;

    protected $config;

    public function __construct(EntityManagerInterface $manager, Select2DebiteurProvider $provider, FichierConfig $config)
    {
        $this->manager  = $manager;
        $this->provider = $provider;
        $this->config   = $config;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class'     => $this->provider->getManagedClass(),
            'multiple'  => false
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->resetViewTransformers();
        $builder->addViewTransformer(new CallbackTransformer(function($objet) {
            return self::encodeTo($objet);
        }, function($data) {
            if(!is_object($data))
                return $this->decodeFrom($data);
        }));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $choices    = [];
        $provider   = $this->provider;


        $view->vars['attr']['data-type']        = 'ajax-select2';
        $view->vars['attr']['class']            = base64_encode($provider->getManagedClass());
        $view->vars['multiple']                 = false;

        if($form->getData() !== null) {
            $choices[] = $this->itemToChoiceView($form->getData());
        }

        $view->vars['base_choices'] = $choices;
    }

    private function itemToChoiceView($item) {

        return new ChoiceView(
            $item,
            self::encodeTo($item),
            $this->provider->toString($item)
        );
    }

    public function getParent()
    {
        return AjaxSelect2DocumentType::class;
    }


    public static function encodeTo($objet) {

        if(!$objet instanceof BaseMembre && !$objet instanceof BaseFamille)
            return null;

        $token = $objet instanceof BaseMembre ? 'membre' : 'famille';
        return $token . ":" . $objet->getId();
    }

    private function decodeFrom($data) {

        list($type, $id) = explode(':', $data);
        if(!in_array($type, ['membre', 'famille']))
            throw new \Exception("Unhandled debiteur type, expected membre or famille, got $type");

        $class = $type === 'membre' ? $this->config->getMembreClass() : $this->config->getFamilleClass();
        return $this->manager->find($class, $id);
    }
}
