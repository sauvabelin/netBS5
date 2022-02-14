<?php

namespace Ovesco\FacturationBundle\Searcher;

use NetBS\CoreBundle\ListModel\Action\ModalAction;
use NetBS\CoreBundle\ListModel\Action\RemoveAction;
use NetBS\CoreBundle\ListModel\ActionItem;
use NetBS\CoreBundle\ListModel\Column\ActionColumn;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Model\BaseSearcher;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\SearchFactureType;
use Ovesco\FacturationBundle\Model\SearchFacture;
use Ovesco\FacturationBundle\Util\FactureListTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class FactureSearcher extends BaseSearcher
{
    use FactureListTrait;

    /**
     * Returns the search form type class
     * @return string
     */
    public function getSearchType()
    {
        return SearchFactureType::class;
    }

    /**
     * Returns an object used to render form, which will contain search data
     * @return object
     */
    public function getSearchObject()
    {
        return new SearchFacture();
    }

    /**
     * Returns the twig template used to render the form. A variable casually named 'form' will be available
     * for you to use
     * @return string
     */
    public function getFormTemplate()
    {
        return "@OvescoFacturation/facture/search_facture.html.twig";
    }
}
