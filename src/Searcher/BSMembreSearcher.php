<?php

namespace App\Searcher;

use NetBS\FichierBundle\Searcher\MembreSearcher;
use App\Form\Search\SearchBaseMembreInformationType;
use App\Model\SearchMembre;

class BSMembreSearcher extends MembreSearcher
{
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
        return 'membre/search_membre.html.twig';
    }

    /**
     * Returns an object used to render form, which will contain search data
     * @return object
     */
    public function getSearchObject()
    {
        return new SearchMembre();
    }

}
