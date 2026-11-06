<?php

namespace App\Automatics;

use NetBS\CoreBundle\Form\Type\SexeType;
use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use App\Model\GarsQuiBougentData;
use App\Settings\BsReferencesTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class GarsQuiBougentAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use FichierConfigTrait, EntityManagerTrait, MembreListHelper, BsReferencesTrait;

    /**
     * @return string
     * Returns this list's name, displayed
     */
    public function getName()
    {
        return "Equipiers / Louveteaux -> gars";
    }

    /**
     * @return string
     * Returns this list's description, displayed
     */
    public function getDescription()
    {
        return "Récupère les gars qui deviennent équipiers ou les louveteaux qui deviennent gars";
    }

    /**
     * @param GarsQuiBougentData $data
     * @return array
     */
    protected function getItems($data = null)
    {
        $fnId       = $this->refs->id($data->getAge() === 'louveteaux' ? 'fonction.louveteau_id' : 'fonction.eclaireur_id');
        if ($fnId === null)
            return [];

        $age        = $data->getAge() === 'louveteaux' ? 10 : 14;
        $year       = intval(date('Y')) - $age;
        $now        = new \DateTime();
        $fonction   = $this->entityManager->getRepository($this->getFichierConfig()->getFonctionClass())
            ->find($fnId);

        $query  = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from($this->getFichierConfig()->getAttributionClass(), 'a');

        $query->where('a.fonction = :fonction')
            ->setParameter('fonction', $fonction)
            ->andWhere('a.dateDebut < :now')
            ->andWhere($query->expr()->orX(
                $query->expr()->isNull('a.dateFin'),
                $query->expr()->gt('a.dateFin', ':now')
            ))
            ->setParameter('now', $now);
        ;

        $attributions   = $query->getQuery()->getResult();
        $membres        = [];
        $groupes        = $this->getGroupes($data);

        /** @var BaseAttribution $attribution */
        foreach($attributions as $attribution)
            if(intval($attribution->getMembre()->getNaissance()->format('Y')) <= $year
                && $attribution->getMembre()->getSexe() == $data->getSexe()
                && in_array($attribution->getGroupe(), $groupes))
                $membres[] = $attribution->getMembre();

        return $membres;
    }

    private function getGroupes(GarsQuiBougentData $data) {

        $louveteaux = $data->getAge() === 'louveteaux';
        $brancheId  = $this->refs->id($data->getSexe() === Personne::HOMME
            ? ($louveteaux ? 'groupe.branche_louveteaux_id' : 'groupe.branche_eclaireurs_id')
            : ($louveteaux ? 'groupe.branche_louvettes_id' : 'groupe.branche_eclaireuses_id'));

        // find(null) throws in Doctrine 2: check the id before looking it up.
        if ($brancheId === null)
            return [];

        /** @var BaseGroupe|null $branche */
        $branche = $this->entityManager->getRepository($this->getFichierConfig()->getGroupeClass())->find($brancheId);
        if ($branche === null)
            return [];

        return array_merge([$branche], $branche->getEnfantsRecursive());
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "sauvabelin.old_gars";
    }

    /**
     * @param FormBuilderInterface $builder
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder)
    {
        $builder->add('sexe', SexeType::class, ['label' => 'Sexe'])
            ->add('age', ChoiceType::class, [
                'label'     => 'Âge',
                'choices'   => [
                    'louveteaux => gars'    => 'louveteaux',
                    'gars => équipiers'     => 'gars'
                ]
            ]);
    }

    /**
     * Returns something that will be injected in the form
     * builder, and available in your automatic
     * @return mixed
     */
    public function buildDataHolder()
    {
        return new GarsQuiBougentData();
    }
}
