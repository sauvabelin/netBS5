<?php

namespace App\Automatics;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\CoreBundle\Utils\Traits\SessionTrait;
use NetBS\FichierBundle\Exporter\PDFEtiquettesV2;
use NetBS\FichierBundle\Mapping\BaseAdresse;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class CDCAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use FichierConfigTrait, EntityManagerTrait, ParamTrait, SessionTrait;

    /**
     * @return string
     * Returns this list's name, displayed
     */
    public function getName()
    {
        return "Coeurs de chêne";
    }

    public function userAuthorization(BaseUser $user)
    {
        return $user->hasRole('ROLE_SG');
    }

    /**
     * @return string
     * Returns this list's description, displayed
     */
    public function getDescription()
    {
        return "Toutes les familles et membres censés reçevoir le coeur de chêne";
    }

    /**
     * @param array $data
     * @return array
     */
    protected function getItems($data = null)
    {
        $adabsId = $this->parameterManager->getValue('bs', 'groupe.adabs_id');
        $membres = !$data['actifs'] ? [] : array_filter($this->entityManager->getRepository($this->fichierConfig->getMembreClass())->findBy(['statut' => BaseMembre::INSCRIT]),
            function(BaseMembre $membre) use ($adabsId) {
                if (count($membre->getAttributions()) === 0) return false;
                foreach ($membre->getAttributions() as $attribution) {
                    if ($attribution->getGroupeId() === intval($adabsId)) {
                        return false;
                    }
                }
                return true;
        });

        $adabs = !$data['adabs'] ? [] : array_map(function(BaseAttribution $attribution) {
            return $attribution->getMembre();
        }, $this->entityManager->getRepository($this->fichierConfig->getGroupeClass())->find($adabsId)->getActivesAttributions());


        $items = array_unique(array_merge($membres, $adabs));

        if ($data['merge'] === 1)
            $items = PDFEtiquettesV2::merge($items);
        else if ($data['merge'] === 2)
            $items = PDFEtiquettesV2::mergeBySameAddress($items);

        $adressables = array_filter($items, function(AdressableInterface $adressable) {
            return $adressable->getSendableAdresse() instanceof BaseAdresse;
        });

        $amountItems = count($items);
        $amountAdressables = count($adressables);

        if ($data['sansAdresses'] && $amountAdressables !== $amountItems) {
            $this->session->getFlashBag()->add('warning', $amountItems - $amountAdressables . " n'ont pas d'adresses !");
        }

        return $data['sansAdresses'] ? $items : $adressables;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "sauvabelin.cdc";
    }

    /**
     * @param FormBuilderInterface $builder
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder)
    {
        $builder->add('merge', ChoiceType::class, ['label' => 'Option de fusion', 'data' => 2, 'choices' => [
            'Aucune fusion' => 0,
            'Fusion par famille' => 1,
            'Fusion par adresse' => 2
        ]])
            ->add('adabs', SwitchType::class, ['label' => 'Inclure l\'Adabs', 'data' => true])
            ->add('actifs', SwitchType::class, ['label' => 'Inclure les actifs', 'data' => true])
            ->add('sansAdresses', SwitchType::class, ['label' => 'Inclure les sans adresses', 'data' => false]);
    }

    /**
     * Returns something that will be injected in the form
     * builder, and available in your automatic
     * @return mixed
     */
    public function buildDataHolder()
    {
        return ['merge' => true];
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return AdressableInterface::class;
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Nom', function($item) {
                return $item instanceof BaseMembre
                    ? $item->getFullName()
                    : $item->__toString();
            }, SimpleColumn::class)
            ->addColumn('Rue', 'sendableAdresse.rue', SimpleColumn::class)
            ->addColumn('NPA', 'sendableAdresse.npa', SimpleColumn::class)
            ->addColumn('Localité', 'sendableAdresse.localite', SimpleColumn::class)
        ;
    }
}
