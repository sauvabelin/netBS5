<?php

namespace NetBS\FichierBundle\Helper;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Service\FichierConfig;

class FamilleHelper extends BaseHelper
{
    protected $config;

    public function __construct(FichierConfig $config)
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
        return $this->twig->render('@NetBSFichier/famille/famille.helper.twig', [
            'famille'   => $item
        ]);
    }

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param BaseFamille $item
     * @return string|null
     */
    public function getRoute($item)
    {
        return $this->router->generate('netbs.fichier.famille.page_famille', ['id' => $item->getId()]);
    }

    /**
     * Returns a string representation of the given item
     * @param BaseFamille $item
     * @return string
     */
    public function getRepresentation($item)
    {
        return $item->__toString();
    }

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass()
    {
        return $this->config->getFamilleClass();
    }
}