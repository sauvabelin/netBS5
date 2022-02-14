<?php

namespace NetBS\FichierBundle\Searcher;

use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\Model\BaseSearcher;
use NetBS\FichierBundle\Form\Search\SearchBaseMembreInformationType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Model\Search\SearchBaseMembreInformation;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class MembreSearcher extends BaseSearcher
{
    use FichierConfigTrait;

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getMembreClass();
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('nom', null, HelperColumn::class)
            ->addColumn('fonction', function(BaseMembre $membre) {
                $a = $membre->getActiveAttribution();
                if($a) return $a->getFonction()->getNom();
            }, SimpleColumn::class)
            ->addColumn('unité', function(BaseMembre $membre) {
                $a = $membre->getActiveAttribution();
                if($a) return $a->getGroupe()->getNom();
            }, SimpleColumn::class)
            ->addColumn('Naissance', 'naissance', DateTimeColumn::class)
        ;
    }

    /**
     * Returns the search form type class
     * @return string
     */
    public function getSearchType()
    {
        return SearchBaseMembreInformationType::class;
    }

    /**
     * Returns the twig template used to render the form. A variable casually named 'form' will be available
     * for you to use
     * @return string
     */
    public function getFormTemplate()
    {
        return '@NetBSFichier/membre/search_membre.html.twig';
    }

    /**
     * Returns an object used to render form, which will contain search data
     * @return object
     */
    public function getSearchObject()
    {
        return new SearchBaseMembreInformation();
    }
}