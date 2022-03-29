<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\ListModel\AjaxModel;
use NetBS\ListBundle\Service\ListEngine;
use NetBS\ListBundle\Service\ListManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AjaxListController extends AbstractController
{
    /**
     * @return Response
     * @Route("/ajax-list/query/{listId}", name="netbs.core.ajax_list_query")
     */
    public function removeItemAction($listId, Request $request, ListManager $listManager, ListEngine $engine) {

        $model = $listManager->getModelById($listId);
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Model $listId must extend the ${AjaxModel::class} class");
        }

        $params = $this->getOrDefault($request->get('params'), json_encode([]));
        $amount = $this->getOrDefault($request->get('amount'), 10);
        $page = $this->getOrDefault($request->get('page'), 0);
        $search = $this->getOrDefault($request->get('search'), null);
        $search = empty($search) ? null : $search;

        foreach (json_decode($params, true) as $key => $value) {
            $model->setParameter($key, $value);
        }

        $model->_setAjaxParams($page, $amount, $search);
        $snapshot = $engine->generateSnaphot($model);
    }

    private function getOrDefault($value, $default) {
        return $value ? $value : $default;
    }
}
