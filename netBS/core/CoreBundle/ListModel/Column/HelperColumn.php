<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\CoreBundle\Service\HelperManager;
use NetBS\CoreBundle\Twig\Extension\HelperExtension;
use NetBS\ListBundle\Column\BaseColumn;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HelperColumn extends BaseColumn
{
    protected $helperManager;

    protected $helperExtension;

    protected $checker;

    public function __construct(HelperExtension $extension, HelperManager $manager, AuthorizationCheckerInterface $checker)
    {
        $this->helperExtension  = $extension;
        $this->helperManager    = $manager;
        $this->checker          = $checker;
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
        if(!$item)
            return "";

        $helper = $this->helperManager->getFor($item);

        $label  = $helper->getRepresentation($item);
        $path   = $helper->getRoute($item);
        $attr   = $this->helperExtension->generateHelperAttribute($item);

        if(!$this->checker->isGranted(CRUD::READ, $item))
            return $label;

        if($path)
            return "<a href='$path' $attr>$label</a>";
        else
            return "<span $attr>$label</span>";
    }
}
