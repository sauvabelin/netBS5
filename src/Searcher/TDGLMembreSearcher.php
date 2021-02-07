<?php

namespace App\Searcher;

use NetBS\FichierBundle\Searcher\MembreSearcher;
use App\Form\TDGLMembreSearchType;
use App\Model\TDGLMembreSearch;

class TDGLMembreSearcher extends MembreSearcher
{
    public function getSearchType()
    {
        return TDGLMembreSearchType::class;
    }

    public function getSearchObject()
    {
        return new TDGLMembreSearch();
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
}
