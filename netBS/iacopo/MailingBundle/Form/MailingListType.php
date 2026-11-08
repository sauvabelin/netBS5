<?php

namespace Iacopo\MailingBundle\Form;

use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Service\MailingDomains;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MailingListType extends AbstractType
{
    public function __construct(private readonly MailingDomains $domains)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la liste',
                'required' => true
            ])
            ->add('baseAddress', TextType::class, [
                'label' => 'Adresse de base',
                'required' => true,
                'attr' => ['placeholder' => 'ex: newsletter@sauvabelin.ch'],
                'help' => ForwardingHelp::for($this->domains)
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => MailingList::class
        ]);
    }
}
