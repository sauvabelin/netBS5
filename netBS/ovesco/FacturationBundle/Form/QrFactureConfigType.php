<?php

namespace Ovesco\FacturationBundle\Form;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Form\PDFConfig\FPDFType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Form\Type\SwitchType;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Model\QrFactureConfig;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QrFactureConfigType extends FPDFType
{
    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $choices = [
            'Modèle attribué' => 'attributed',
            'Réévaluer les règles' => 'rules',
        ];
        $models = $this->manager->getRepository(FactureModel::class)->findAll();
        foreach ($models as $model) $choices[$model->getName()] = 'force_' . $model->getId();
        $builder
            ->add('model', ChoiceType::class, [
                'label' => 'Modèle à utiliser',
                'choices' => $choices,
            ])
            ->add('adresseLeft', NumberType::class, ['label' => 'Gauche adresse postale'])
            ->add('adresseTop', NumberType::class, ['label' => 'Haut adresse postale'])
            ->add('date', DatepickerType::class, [
                'label' => 'Date sur la facture',
                'required' => false,
            ])
            ->add('border', SwitchType::class, ['label' => 'Repères visuels'])
            ->add('sortAlpha', SwitchType::class, ['label' => 'Trier par ordre alphabétique'])
            ->add('groupByBranche', SwitchType::class, ['label' => 'Grouper par branche'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => QrFactureConfig::class]);
    }
}
