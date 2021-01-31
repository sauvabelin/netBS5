<?php

namespace App\Automatics;

use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class CotisationsAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use MembreListHelper, FichierConfigTrait, EntityManagerTrait, ParamTrait;

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
        $lvtx = $this->getMembresLouveteauxEtSMT();
        $normal = $this->getToutLeReste();
        $cleanLvtx = $this->filterGarsFromChefs(array_diff($lvtx, $normal));

        return $data['type'] === 'lvtx' ? $cleanLvtx['gars'] : array_merge($normal, $cleanLvtx['chefs']);
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
                    'Louveteaux & SMT'      => 'lvtx',
                    'Cotisations normales'  => 'gars',
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

    private function getMembresLouveteauxEtSMT() {

        $louveteaux = $this->extractMembresFromGroupeTypes([$this->parameterManager->getValue('bs', 'groupe_type.meute_id')]);
        $smt = $this->extractMembresFromGroupes([
            $this->entityManager->find($this->getFichierConfig()->getGroupeClass(), $this->parameterManager->getValue('bs', 'groupe.branche_smt_id'))
        ]);
        return array_unique(array_merge($louveteaux, $smt));
    }

    private function getToutLeReste() {

        $membres = $this->extractMembresFromGroupeTypes([
            $this->parameterManager->getValue('bs', 'groupe_type.troupe_id'),
            $this->parameterManager->getValue('bs', 'groupe_type.clan_id'),
            $this->parameterManager->getValue('bs', 'groupe_type.edc_id'),
            $this->parameterManager->getValue('bs', 'groupe_type.equipe_interne_id'),
        ]);

        $smt = $this->extractMembresFromGroupes([
            $this->entityManager->find($this->getFichierConfig()->getGroupeClass(), $this->parameterManager->getValue('bs', 'groupe.branche_smt_id'))
        ]);
        return array_diff($membres, $smt);
    }

    private function extractMembresFromGroupeTypes($groupeTypes) {

        $groupeRepo = $this->entityManager->getRepository($this->getFichierConfig()->getGroupeClass());
        $query = $groupeRepo->createQueryBuilder('g');
        $groupes = $query->where($query->expr()->in('g.groupeType', $groupeTypes))->getQuery()->getResult();

        return $this->extractMembresFromGroupes($groupes);
    }

    private function extractMembresFromGroupes($groupes) {

        $membres = [];
        /** @var BaseGroupe $groupe */
        foreach($groupes as $groupe)
            foreach($groupe->getActivesRecursivesAttributions() as $attribution)
                $membres[] = $attribution->getMembre();


        return array_unique($membres);
    }

    private function filterGarsFromChefs($membres) {

        $chefs = [];
        $gars = [];
        /** @var BaseMembre $membre */
        foreach($membres as $membre) {
            $small = true;
            foreach($membre->getActivesAttributions() as $attribution)
                if($attribution->getFonction()->getPoids() > 99)
                    $small = false;

            if ($small) $gars[] = $membre;
            else $chefs[] = $membre;
        }

        return [
            'chefs' => $chefs,
            'gars' => $gars,
        ];
    }
}
