<?php

namespace NetBS\FichierBundle\Helper;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class MembreHelper extends BaseHelper
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    /**
     * Renders a helper view for the given item
     * @param BaseMembre $item
     * @return string
     */
    public function render($item)
    {
        return $this->twig->render('@NetBSFichier/membre/membre.helper.twig', [
            'membre'    => $item
        ]);
    }

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param BaseMembre $item
     * @return string|null
     */
    public function getRoute($item)
    {
        return $this->router->generate('netbs.fichier.membre.page_membre', ['id' => $item->getId()]);
    }

    /**
     * Returns a string representation of the given item
     * @param BaseMembre $item
     * @return string
     */
    public function getRepresentation($item)
    {
        return $item->getFullName();
    }

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass()
    {
        return $this->config->getMembreClass();
    }
}