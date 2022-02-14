<?php

namespace NetBS\FichierBundle\Form\Export;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\FichierBundle\Exporter\Config\EtiquettesV2Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtiquettesV2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('horizontalMargin', NumberType::class, ['label' => 'Marge horizontale page'])
            ->add('verticalMargin', NumberType::class, ['label' => 'Marge verticale page'])
            ->add('rows', NumberType::class, ['label' => 'Nombre de lignes'])
            ->add('columns', NumberType::class, ['label' => 'Nombre de colonnes'])
            ->add('paddingLeft', NumberType::class, ['label' => 'Marge interne gauche'])
            ->add('paddingTop', NumberType::class, ['label' => 'Marge interne haut'])
            ->add('fontSize', NumberType::class, ['label' => 'Taille police'])
            ->add('interligne', NumberType::class, ['label' => 'Interligne'])
            ->add('economies', TextType::class, ['label' => 'Economies de papier', 'required' => false])
            //->add('mergeFamilles', SwitchType::class, ['label' => "Fusion des familles"])
            ->add('mergeOption', ChoiceType::class, ['label' => 'Option de fusion', 'choices' => [
                'Aucune fusion' => 0,
                'Par famille' => 1,
                'Par adresse' => 2,
            ]])
            ->add('infoPage', SwitchType::class, ['label' => "Page d'info"])
            ->add('reperes', SwitchType::class, ['label' => "Repères visuels"])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', EtiquettesV2Config::class);
    }
}
