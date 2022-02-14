<?php

namespace Ovesco\FacturationBundle\Helper;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use Ovesco\FacturationBundle\Entity\Facture;

class FactureHelper extends BaseHelper
{
    /**
     * Renders a helper view for the given item
     * @param $item
     * @return string
     * @throws
     */
    public function render($item)
    {
        return $this->twig->render('@OvescoFacturation/facture/facture.helper.twig', ['facture' => $item]);
    }

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param Facture $item
     * @return string|null
     */
    public function getRoute($item)
    {
        return null;
    }

    /**
     * Returns a string representation of the given item
     * @param Facture $item
     * @return string
     */
    public function getRepresentation($item)
    {
        return "#" . $item->getFactureId();
    }

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass()
    {
        return Facture::class;
    }
}