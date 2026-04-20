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
    #[Route('/ajax-list/query/{listId}', name: 'netbs.core.ajax_list_query')]
    public function queryAction($listId, Request $request, ListManager $listManager, ListEngine $engine) {

        $model = $listManager->getModelByAlias($listId);
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Model $listId must extend the AjaxModel class");
        }

        $params = json_decode($request->getContent(), true);
        $amount = intval($this->getOrDefault($request->get('amount'), 10));
        $page = intval($this->getOrDefault($request->get('page'), 0));
        $search = $this->getOrDefault($request->get('search'), null);
        $search = empty($search) ? null : $search;


        foreach ($params ?? [] as $key => $value) {
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

    #[Route('/ajax-list/html/{listId}', name: 'netbs.core.ajax_list_html')]
    public function htmlAction($listId, Request $request, ListManager $listManager, ListEngine $engine): Response {

        $model = $listManager->getModelByAlias($listId);
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Model $listId must extend the AjaxModel class");
        }

        $amount = intval($this->getOrDefault($request->query->get('amount'), 10));
        $page = intval($this->getOrDefault($request->query->get('page'), 0));
        $search = $this->getOrDefault($request->query->get('search'), null);
        $search = empty($search) ? null : $search;
        $tableId = $this->getOrDefault($request->query->get('tableId'), 'list');

        // Decode model parameters from query string
        $paramsJson = $request->query->get('params');
        $params = $paramsJson ? json_decode($paramsJson, true) : [];

        foreach ($params as $key => $value) {
            $model->setParameter($key, $value);
        }

        $model->_setAjaxParams($page, $amount, $search);

        // Count total items matching the search filter (before pagination)
        $totalItems = $model->countFilteredItems();

        $snapshot = $engine->generateSnaphot($model);

        // Build row data with IDs
        $rows = [];
        for ($i = 0; $i < count($snapshot->getData()); $i++) {
            $row = $snapshot->getData()[$i];
            $item = $model->getElements()[$i];
            $rows[] = [
                'id' => $item->getId(),
                'cells' => $row,
            ];
        }

        // All IDs (unfiltered) for the checkbox-select controller
        $allIds = $model->retrieveAllIds();

        return $this->render('@NetBSCore/renderer/ajax.frame.twig', [
            'rows' => $rows,
            'headers' => $snapshot->getHeaders(),
            'tableId' => $tableId,
            'page' => $page,
            'amount' => $amount,
            'search' => $search ?? '',
            'totalItems' => $totalItems,
            'allIds' => $allIds,
            'listId' => $listId,
            'params' => $params,
            'hasSearch' => count($model->searchTerms()) > 0,
        ]);
    }

    #[Route('/netbs-list/html/{listId}', name: 'netbs.core.netbs_list_html')]
    public function netbsHtmlAction($listId, Request $request, ListManager $listManager, ListEngine $engine): Response {

        $model = $listManager->getModelByAlias($listId);

        $amount = intval($this->getOrDefault($request->query->get('amount'), 10));
        $page = intval($this->getOrDefault($request->query->get('page'), 0));
        $search = $this->getOrDefault($request->query->get('search'), null);
        $search = empty($search) ? null : $search;
        $tableId = $this->getOrDefault($request->query->get('tableId'), 'list');

        // Decode model parameters from query string
        $paramsJson = $request->query->get('params');
        $params = $paramsJson ? json_decode($paramsJson, true) : [];

        foreach ($params as $key => $value) {
            $model->setParameter($key, $value);
        }

        // Generate full snapshot (all items — BaseListModel returns everything)
        $snapshot = $engine->generateSnaphot($model);

        // Build full rows array with IDs
        $allRows = [];
        $elements = $model->getElements();
        $elements = is_array($elements) ? array_values($elements) : iterator_to_array($elements, false);
        $data = $snapshot->getData();
        for ($i = 0; $i < count($data); $i++) {
            $allRows[] = [
                'id' => $elements[$i]->getId(),
                'cells' => $data[$i],
            ];
        }

        $allIds = array_map(fn($el) => $el->getId(), $elements);

        // Apply in-memory text search across rendered cell values
        if ($search) {
            $allRows = array_values(array_filter($allRows, function($row) use ($search) {
                foreach ($row['cells'] as $cell) {
                    if (stripos(strip_tags((string)$cell), $search) !== false) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $totalItems = count($allRows);

        // Paginate
        $offset = $page * $amount;
        $rows = array_slice($allRows, $offset, $amount);

        return $this->render('@NetBSCore/renderer/ajax.frame.twig', [
            'rows' => $rows,
            'headers' => $snapshot->getHeaders(),
            'tableId' => $tableId,
            'page' => $page,
            'amount' => $amount,
            'search' => $search ?? '',
            'totalItems' => $totalItems,
            'allIds' => $allIds,
            'listId' => $listId,
            'params' => $params,
            'hasSearch' => true,
            'baseUrl' => $this->generateUrl('netbs.core.netbs_list_html', ['listId' => $listId]),
        ]);
    }

    private function getOrDefault($value, $default) {
        return empty($value) ? $default : $value;
    }
}
