<?php

namespace App\Automatics;

use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Service\Attribute\Required;
use App\Settings\BsReferencesTrait;

class CotisationsAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use MembreListHelper, FichierConfigTrait, EntityManagerTrait, ParamTrait, BsReferencesTrait;

    private RuleEvaluator $ruleEvaluator;

    #[Required]
    public function setRuleEvaluator(RuleEvaluator $ruleEvaluator): void
    {
        $this->ruleEvaluator = $ruleEvaluator;
    }

    /**
     * @return string
     * Returns this list's name, displayed
     */
    public function getName()
    {
        return "Cotisations";
    }

    /**
     * @return string
     * Returns this list's description, displayed
     */
    public function getDescription()
    {
        return "Liste des membres à cotiser en fonction des attributions";
    }

    /**
     * @param array $data
     * @return array
     */
    protected function getItems($data = null)
    {
        $allMembres = $this->getAllMembres();
        $split = $this->splitTarif($allMembres);

        return $data['type'] === 'normal' ? $split['normal'] : $split['reduit'];
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "sauvabelin.cotisations";
    }

    /**
     * @param FormBuilderInterface $builder
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder)
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label'     => 'Type',
                'choices'   => [
                    'Normal'    => 'normal',
                    'Réduit'    => 'reduit',
                ]
            ]);
    }

    public function isAllowed(BaseUser $user)
    {
        return $user->hasRole('ROLE_TRESORIER');
    }

    /**
     * Returns something that will be injected in the form
     * builder, and available in your automatic
     * @return mixed
     */
    public function buildDataHolder()
    {
        return ['type' => null];
    }

    private function getAllMembres() {

        $groupeRepo = $this->entityManager->getRepository($this->getFichierConfig()->getGroupeClass());
        $groupes = $groupeRepo->findAll();

        $membres = [];
        /** @var BaseGroupe $groupe */
        foreach($groupes as $groupe)
            foreach($groupe->getActivesRecursivesAttributions() as $attribution)
                $membres[] = $attribution->getMembre();

        return array_filter(array_unique($membres), fn($m) => $m->consideredInscrit());
    }

    /**
     * Membres matching the « tarif réduit » rule pay the reduced rate. Without a rule, the legacy
     * logic applies: a fonction of poids > 99, or an attribution in the Someo branche or one of its
     * direct sub-groupes (legacy key bs.groupe.branche_smt_id).
     *
     * @return array{normal: BaseMembre[], reduit: BaseMembre[]}
     */
    public function splitTarif($membres) {

        $rule = $this->parameterManager->getValue('bs', 'cotisations.tarif_reduit');
        if ($rule !== null) {
            $split = ['normal' => [], 'reduit' => []];
            foreach ($membres as $membre)
                $split[$this->ruleEvaluator->matches($rule, 'membre', $membre) ? 'reduit' : 'normal'][] = $membre;

            return $split;
        }

        $smtId = $this->refs->id('groupe.branche_smt_id');

        $normal = [];
        $reduit = [];
        /** @var BaseMembre $membre */
        foreach($membres as $membre) {
            $isReduit = false;

            foreach($membre->getActivesAttributions() as $attribution) {
                if($attribution->getFonction()->getPoids() > 99) {
                    $isReduit = true;
                    break;
                }
                if($smtId !== null && ($attribution->getGroupe()->getId() == $smtId || ($attribution->getGroupe()->getParent() && $attribution->getGroupe()->getParent()->getId() == $smtId))) {
                    $isReduit = true;
                    break;
                }
            }

            if ($isReduit) $reduit[] = $membre;
            else $normal[] = $membre;
        }

        return [
            'normal' => $normal,
            'reduit' => $reduit,
        ];
    }
}
