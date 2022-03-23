<?php

namespace App\Form;

use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class NewsChannelBotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du bot'])
            ->add('description', TextType::class, ['label' => 'Description du bot'])
            ->add('channels', Select2DocumentType::class, [
                'label' => "Channels source",
                'class' => NewsChannel::class,
                'multiple' => true
            ])
        ;
    }
}
