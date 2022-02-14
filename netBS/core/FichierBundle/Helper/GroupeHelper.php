<?php

namespace NetBS\FichierBundle\Helper;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class GroupeHelper extends BaseHelper
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    /**
     * Renders a helper view for the given item
     * @param BaseGroupe $item
     * @return string
     */
    public function render($item)
    {
        return $this->twig->render('@NetBSFichier/groupe/groupe.helper.twig', [
            'groupe'    => $item
        ]);
    }

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param BaseGroupe $item
     * @return string|null
     */
    public function getRoute($item)
    {
        return $this->router->generate('netbs.fichier.groupe.page_groupe', ['id' => $item->getId()]);
    }

    /**
     * Returns a string representation of the given item
     * @param BaseGroupe $item
     * @return string
     */
    public function getRepresentation($item)
    {
        return $item->getNom();
    }

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass()
    {
        return $this->config->getGroupeClass();
    }
}