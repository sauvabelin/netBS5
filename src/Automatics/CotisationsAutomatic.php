<?php

namespace App\Automatics;

use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class CotisationsAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use MembreListHelper, FichierConfigTrait, EntityManagerTrait;

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
        $split = $this->filterGarsFromChefs($allMembres);

        return $data['type'] === 'participant' ? $split['gars'] : $split['chefs'];
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
                    'Participant'   => 'participant',
                    'Chef'          => 'chef',
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
