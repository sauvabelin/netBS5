<?php

namespace Ovesco\FacturationBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Form\PDFConfig\FPDFType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use Ovesco\FacturationBundle\Model\FactureConfig;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureConfigType extends FPDFType
{
    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = ['null' => 'laisser faire'];
        $models = $this->manager->getRepository('OvescoFacturationBundle:FactureModel')->findAll();
        foreach ($models as $model) $choices[$model->getId()] = $model->getName();
        parent::buildForm($builder, $options);
        $builder
            ->add('model', ChoiceType::class, [
                'label' => 'Modèle à utiliser',
                'choices' => array_flip($choices)
            ])
            ->add('date', DatepickerType::class, [
                'label' => 'Date sur la facture',
                'required' => false,
            ])
            ->add('adresseLeft', NumberType::class, ['label' => 'Gauche adresse postale'])
            ->add('adresseTop', NumberType::class, ['label' => 'Haut adresse postale'])
            ->add('wg', NumberType::class, ['label' => "Décalement gauche BVR"])
            ->add('hg', NumberType::class, ['label' => "Ligne de codage gauche"])
            ->add('haddr', NumberType::class, ['label' => 'Position Y adresses haut'])
            ->add('waddr', NumberType::class, ['label' => 'Décalage X adresse droite'])
            ->add('wccp', NumberType::class, ['label' => "Position X du CCP"])
            ->add('hccp', NumberType::class, ['label' => "Position Y du CCP"])
            ->add('wd', NumberType::class, ['label' => "Décalage droite ligne+addr"])
            ->add('hd', NumberType::class, ['label' => "Décalage haut ligne+addr"])
            ->add('wb', NumberType::class, ['label' => "position X num. référence"])
            ->add('hb', NumberType::class, ['label' => "position Y num. référence"])
            ->add('bvrIl', NumberType::class, ['label' => 'Interligne BVR'])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => FactureConfig::class]);
    }
}
