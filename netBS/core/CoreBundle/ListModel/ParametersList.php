<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParametersList extends BaseListModel
{
    use EntityManagerTrait;

    public function __construct(private SettingsRegistry $registry)
    {
    }

    /**
     * Only the legacy parameters: declared settings are edited through their typed form.
     * @return array
     */
    protected function buildItemsList()
    {
        $parameters = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Parameter::class, 'p')
            ->orderBy('p.namespace', 'DESC')
            ->getQuery()
            ->execute();

        return array_values(array_filter($parameters,
            fn(Parameter $p) => $this->registry->get($p->getNamespace(), $p->getKey()) === null));
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return Parameter::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.core.parameters';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Section', 'namespace', SimpleColumn::class)
            ->addColumn('Clé', 'key', SimpleColumn::class)
            ->addColumn('Valeur', null, XEditableColumn::class, [
                XEditableColumn::TYPE_CLASS => TextType::class,
                XEditableColumn::PROPERTY   => 'value'
            ]);
    }
}