<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\ListModel\AjaxModel;
use NetBS\ListBundle\Service\ListEngine;
use NetBS\ListBundle\Service\ListManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        $model = $listManager->getModelByAlias($listId);
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Model $listId must extend the AjaxModel class");
        }

        $params = json_decode($request->getContent(), true);
        $amount = intval($this->getOrDefault($request->get('amount'), 10));
        $page = intval($this->getOrDefault($request->get('page'), 0));
        $search = $this->getOrDefault($request->get('search'), null);
        $search = empty($search) ? null : $search;


        foreach ($params as $key => $value) {
            $model->setParameter($key, $value);
        }

        $model->_setAjaxParams($page, $amount, $search);
        $snapshot = $engine->generateSnaphot($model);

        $res = [];
        // Generate a fancy response
        for ($i = 0; $i < count($snapshot->getData()); $i++) {
            $row = $snapshot->getData()[$i];
            $item = $model->getElements()[$i];
            $vals = [];
            foreach ($row as $value) {
                $vals[] = $value;
            }

            $res[] = [
                'id' => $item->getId(),
                'row' => $vals,
            ];
        }

        return new JsonResponse($res);
    }

    private function getOrDefault($value, $default) {
        return empty($value) ? $default : $value;
    }
}
