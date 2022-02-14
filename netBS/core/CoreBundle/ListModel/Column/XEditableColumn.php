<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\CoreBundle\Twig\Extension\XEditableExtension;
use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class XEditableColumn extends BaseColumn
{

    const TYPE_CLASS    = 'type_class';
    const PROPERTY      = 'property';
    const PARAMS        = 'params';

    protected $extension;

    public function __construct(XEditableExtension $editableExtension)
    {
        $this->extension    = $editableExtension;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(self::TYPE_CLASS)
            ->setRequired(self::PROPERTY)
            ->setDefault(self::PARAMS, []);
    }

    /**
     * Return content related to the given object with the given params
     * @param object $item
     * @param array $params
     * @return string
     */
    public function getContent($item, array $params = [])
    {
        $type       = $params[self::TYPE_CLASS];
        $property   = $params[self::PROPERTY];
        $typeparams = $params[self::PARAMS];

        unset($params[self::TYPE_CLASS]);
        unset($params[self::PROPERTY]);
        unset($params[self::PARAMS]);

        return $this->extension->toXeditable($item, $property, $type, $typeparams);
    }
}
