<?php

namespace NetBS\CoreBundle\Model;

use Symfony\Component\Form\FormInterface;

class SearchInstance
{
    /**
     * @var BaseSearcher
     */
    protected $searcher;

    /**
     * @var FormInterface
     */
    protected $form;

    public function __construct(BaseSearcher $searcher, FormInterface $form)
    {
        $this->searcher = $searcher;
        $this->form     = $form;
    }

    /**
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * @return BaseSearcher
     */
    public function getSearcher()
    {
        return $this->searcher;
    }
}