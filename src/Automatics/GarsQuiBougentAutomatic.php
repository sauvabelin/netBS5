<?php

namespace App\Automatics;

use NetBS\CoreBundle\Form\Type\SexeType;
use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use App\Model\GarsQuiBougentData;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class GarsQuiBougentAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use FichierConfigTrait, ParamTrait, EntityManagerTrait, MembreListHelper;

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
        $fnId       = $data->getAge() === 'louveteaux'
            ? $this->parameterManager->getValue('bs', 'fonction.louveteau_id')
            : $this->parameterManager->getValue('bs', 'fonction.eclaireur_id');

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

        /** @var BaseGroupe $branche */
        $branche    = null;
        $repo       = $this->entityManager->getRepository($this->getFichierConfig()->getGroupeClass());

        if($data->getSexe() === Personne::HOMME) {

            if($data->getAge() === 'louveteaux')
                $branche = $repo->find($this->parameterManager->getValue('bs', 'groupe.branche_louveteaux_id'));
            else
                $branche = $repo->find($this->parameterManager->getValue('bs', 'groupe.branche_eclaireurs_id'));
        }

        else {
            if($data->getAge() === 'louveteaux')
                $branche = $repo->find($this->parameterManager->getValue('bs', 'groupe.branche_louvettes_id'));
            else
                $branche = $repo->find($this->parameterManager->getValue('bs', 'groupe.branche_eclaireuses_id'));
        }

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
