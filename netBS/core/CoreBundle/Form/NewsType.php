<?php

namespace NetBS\CoreBundle\Form;

use NetBS\CoreBundle\Entity\News;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\Form\Type\QuillType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("channel", Select2DocumentType::class, [
                'label'         => 'Channel',
                'class'         => NewsChannel::class,
                'choice_label'  => "nom"
            ])
            ->add('pinned', SwitchType::class, ['label' => "Epinglée"])
            ->add("titre", TextType::class, ['label' => "Titre"])
            ->add("contenu", QuillType::class, ['label' => "Contenu"])
        ;
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => News::class
        ));
    }
}
