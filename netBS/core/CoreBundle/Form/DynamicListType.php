<?php

namespace NetBS\CoreBundle\Form;

use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\CoreBundle\Service\DynamicListManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DynamicListType extends AbstractType
{
    protected $dlm;

    protected $bridges;

    public function __construct(DynamicListManager $manager, ListBridgeManager $bridges)
    {
        $this->dlm  = $manager;
        $this->bridges = $bridges;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('itemClass', null);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $itemClass = $options['itemClass'];
        $choices = [];
        $actual = array_flip(array_flip($this->dlm->getManagedClasses()));

        if($itemClass) {
            if (isset($actual[$itemClass]))
                $choices = [$actual[$itemClass] => $itemClass];

            else {
                foreach ($this->dlm->getManagedClasses() as $name => $managedClass)
                    if ($this->bridges->isValidTransformation($itemClass, $managedClass))
                        $choices[$name . " (par conversion)"] = $managedClass;
            }
        } else $choices = $this->dlm->getManagedClasses();

        $builder
            ->add('name', TextType::class, array('label' => 'Nom de la liste'))
            ->add('itemsClass', ChoiceType::class, array(
                'label'     => 'Éléments contenus',
                'choices'   => $choices
            ))
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            /** @var DynamicList $list */
            $list   = $event->getData();
            if(empty($list->getItemsClass()))
                $event->getForm()->get('itemsClass')->getConfig()->getOptions()['disabled'] = true; //TODO make this work, supposed to hide itemsClass if created from a list model button
        });
    }
}
