<?php

namespace NetBS\ListBundle\Column;

use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeColumn extends BaseColumn
{
    private $params;

    public function __construct(ParameterManager $params)
    {
        $this->params   = $params;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('format', $this->params->getValue('format', 'php_date'));
    }

    /**
     * Return content related to the given object with the given params
     * @param object $item
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function getContent($item, array $params = [])
    {
        if($item === null)
            return '';

        if($item instanceof \DateTime || $item instanceof \DateTimeImmutable)
            return $item->format($params['format']);

        throw new \Exception("Object is not a DateTime!");
    }
}
