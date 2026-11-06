<?php

namespace Ovesco\FacturationBundle\Form;

use NetBS\CoreBundle\Form\Type\RuleType;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Service\FactureTextRenderer;
use Ovesco\FacturationBundle\Service\LegacyFactureText;
use Ovesco\FacturationBundle\Validator\FactureText;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureModelType extends AbstractType
{
    /** Printed raw by APMBSFactureExporter, maintained by another group: never converted or checked. */
    const APMBS = 'APMBS';

    const TEXTS = [
        'titre'             => [TextType::class, 'Titre de la facture'],
        'topDescription'    => [TextareaType::class, 'Texte du haut'],
        'bottomSalutations' => [TextareaType::class, 'Texte du bas (salutations)'],
    ];

    public function __construct(private FactureTextRenderer $renderer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom du modèle'])
            ->add('poids', NumberType::class, ['label' => 'Poids', 'help' => 'Si plusieurs modèles conviennent, celui au poids le plus élevé est utilisé.'])
            ->add('ruleJson', RuleType::class, [
                'context' => 'facture',
                'label'      => 'Quand utiliser ce modèle ?',
                'empty_text' => 'Toujours (aucune condition).',
            ])
            ->add('signataire', TextType::class, ['label' => "Signataire"])
            ->add('groupName', TextType::class, ['label' => 'Expéditeur', 'help' => "Imprimé en haut de la facture avec l'adresse."])
            ->add('rue', TextType::class, ['label' => "Rue"])
            ->add('npaVille', TextType::class, ['label' => "NPA et ville"])
            ->add('cityFrom', TextType::class, ['label' => 'Lieu (avant la date)', 'help' => 'ex. « Lausanne », imprimé « Lausanne le 06.11.2026 »'])
        ;

        // Old-format texts are shown converted to the new format; saving stores them as such.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var FactureModel|null $model */
            $model     = $event->getData();
            $apmbs     = $model?->getName() === self::APMBS;
            $converted = $model !== null && !$apmbs && $model->getTextesFormat() === FactureModel::TEXTES_ANCIEN;

            foreach (self::TEXTS as $field => [$type, $label]) {
                $value   = $model ? $model->{'get' . ucfirst($field)}() : null;
                $shown   = $converted ? LegacyFactureText::convert($value) : $value;
                $options = [
                    'label'       => $label,
                    'constraints' => $apmbs ? [] : [new FactureText(previous: $shown)],
                    'attr'        => ['data-placeholder-picker-target' => 'input'],
                ];
                if ($converted) {
                    $options['data'] = $shown;
                }
                $event->getForm()->add($field, $type, $options);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $model = $event->getData();
            if ($model instanceof FactureModel && $model->getName() !== self::APMBS) {
                $model->setTextesFormat(FactureModel::TEXTES_NOUVEAU);
            }
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $model = $form->getData();
        $view->vars['placeholders']     = $this->renderer->placeholders();
        $view->vars['textes_converted'] = $model instanceof FactureModel && $model->getName() !== self::APMBS
            && $model->getTextesFormat() === FactureModel::TEXTES_ANCIEN;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => FactureModel::class]);
    }
}
