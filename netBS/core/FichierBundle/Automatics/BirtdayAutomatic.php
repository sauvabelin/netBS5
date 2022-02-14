<?php

namespace NetBS\FichierBundle\Automatics;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Model\Automatic\BirthdayData;
use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Model\ConfigurableAutomaticInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use Symfony\Component\Form\FormBuilderInterface;

class BirtdayAutomatic extends BaseAutomatic implements ConfigurableAutomaticInterface
{
    use MembreListHelper, FichierConfigTrait, EntityManagerTrait;

    /**
     * @return string
     */
    public function getName()
    {
        return "Anniversaires";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Retournes la liste des gens nés entre deux jours donnés pour leurs anniversaires";
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'core.automatic.birthday';
    }

    /**
     * @param FormBuilderInterface $builder
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder)
    {
        $builder->add('from', DatepickerType::class, array('label'  => 'Entre le'));
        $builder->add('to', DatepickerType::class, array('label'  => 'Et le'));
    }

    /**
     * @param BirthdayData $data
     * @return array|mixed
     */
    protected function getItems($data = null)
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from($this->getFichierConfig()->getMembreClass(), 'm')
            ->where('DAYOFYEAR(m.naissance) >= :startDay')
            ->andWhere('DAYOFYEAR(m.naissance) <= :endDay')
            ->setParameter('startDay', intval($data->getFrom()->format('z')) + 1)
            ->setParameter('endDay', intval($data->getTo()->format('z')) + 1)
            ->getQuery()
            ->execute();
    }

    /**
     * Returns something that will be injected in the form
     * builder, and available in your automatic
     * @return mixed
     */
    public function buildDataHolder()
    {
        return new BirthdayData();
    }
}