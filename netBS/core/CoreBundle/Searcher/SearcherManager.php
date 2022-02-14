<?php

namespace NetBS\CoreBundle\Searcher;

use NetBS\CoreBundle\Model\BaseSearcher;
use NetBS\CoreBundle\Model\SearchInstance;
use NetBS\CoreBundle\Service\QueryMaker;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class SearcherManager
{
    /**
     * @var BaseSearcher[]
     */
    protected $searcbers = [];

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var FormFactoryInterface
     */
    protected $factory;

    /**
     * @var null|\Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var QueryMaker
     */
    protected $queryMaker;

    public function __construct(RequestStack $requestStack, Environment $twig, FormFactoryInterface $factory, QueryMaker $queryMaker) {

        $this->twig         = $twig;
        $this->queryMaker   = $queryMaker;
        $this->factory      = $factory;
        $this->request      = $requestStack->getCurrentRequest();
    }

    /**
     * @param BaseSearcher $searcher
     */
    public function registerSearcher(BaseSearcher $searcher) {
        $this->searcbers[$searcher->getManagedItemsClass()] = $searcher;
    }

    /**
     * @param $class
     * @return BaseSearcher
     * @throws \Exception
     */
    public function getSearcher($class) {

        if(!isset($this->searcbers[$class]))
            throw new \Exception("No searcher found for class $class");

        return $this->searcbers[$class];
    }

    /**
     * @param string $class
     * @return SearchInstance
     */
    public function bind($class) {

        $searcher   = $this->getSearcher($class);
        $form       = $this->factory->create($searcher->getSearchType(), $searcher->getSearchObject());

        $form->handleRequest($this->request);

        if($form->isSubmitted() && $form->isValid())
            $searcher->setForm($form);

        return new SearchInstance($searcher, $form);
    }

    /**
     * @param $searcher
     * @param $form
     * @return SearchInstance
     */
    public function bindForm($searcher, $form) {

        return new SearchInstance($searcher, $form);
    }

    public function render(SearchInstance $instance, array $params = []) {

        $previous = $this->getPreviousIds();
        $previous = is_array($previous) ? $previous : [];
        if ($this->merge()) $instance->getSearcher()->addPreviousResults($previous);

        $currentIds = array_unique(array_merge(
            array_map(function($item) { return $item->getId(); }, $instance->getSearcher()->getResults()),
            $previous
        ));

        $form   = $instance->getForm();
        return new Response($this->twig->render($instance->getSearcher()->getFormTemplate(), array_merge($params, [
            'form'          => $form->createView(),
            'searcher'      => $instance->getSearcher(),
            'merge'         => $this->merge(),
            'currentIds'    => $this->merge() ? serialize($currentIds) : serialize([]),
        ])));
    }

    private function merge() {
        return $this->request->get('merge_with_previous') !== null;
    }

    private function getPreviousIds() {
        return unserialize($this->request->request->get('previous_results'));
    }
}
