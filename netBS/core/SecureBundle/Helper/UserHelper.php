<?php

namespace NetBS\SecureBundle\Helper;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;

class UserHelper extends BaseHelper
{
    use EntityManagerTrait;

    protected $config;

    public function __construct(SecureConfig $config)
    {
        $this->config   = $config;
    }

    /**
     * Renders a helper view for the given item
     * @param $item
     * @return string
     */
    public function render($item)
    {
    }

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param BaseUser $item
     * @return string|null
     */
    public function getRoute($item)
    {
        if(!$item->getMembre())
            return null;

        return $this->router->generate('netbs.fichier.membre.page_membre', ['id' => $item->getMembre()->getId()]);
    }

    /**
     * Returns a string representation of the given item
     * @param BaseUser $item
     * @return string
     */
    public function getRepresentation($item)
    {
        if(!$item->getMembre())
            return $item->getUsername();

        return $item->getUsername() . " (" . $item->getMembre()->getFullName() . ")";
    }

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass()
    {
        return $this->config->getUserClass();
    }
}