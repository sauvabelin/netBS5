<?php

namespace NetBS\ListBundle\Column;

use NetBS\CoreBundle\Utils\DateFormat;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeColumn extends BaseColumn
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('format', DateFormat::DATE);
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
