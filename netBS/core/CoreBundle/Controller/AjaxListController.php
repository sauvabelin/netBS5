<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\ListModel\AjaxModel;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\SnapshotTable;
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

        $model = $this->requireAjaxModel($listManager, $listId);

        $params = json_decode($request->getContent(), true);
        [$page, $amount, $search] = $this->extractPaginationFromRequest($request->query);

        $this->applyParamsToModel($model, $params ?? []);
        $model->_setAjaxParams($page, $amount, $search);

        $snapshot = $engine->generateSnaphot($model);

        return new JsonResponse($this->pairElementsWithRows($snapshot, $model));
    }

    #[Route('/ajax-list/html/{listId}', name: 'netbs.core.ajax_list_html')]
    public function htmlAction($listId, Request $request, ListManager $listManager, ListEngine $engine): Response {

        $model = $this->requireAjaxModel($listManager, $listId);

        [$page, $amount, $search] = $this->extractPaginationFromRequest($request->query);
        $tableId = $this->getOrDefault($request->query->get('tableId'), 'list');
        $params  = $this->decodeParams($request->query->get('params'));

        $this->applyParamsToModel($model, $params);
        $model->_setAjaxParams($page, $amount, $search);

        $totalItems = $model->countFilteredItems();
        $snapshot   = $engine->generateSnaphot($model);
        $rows       = $this->buildRowsWithIds($snapshot, $model);

        return $this->render('@NetBSCore/renderer/ajax.frame.twig', [
            'rows' => $rows,
            'headers' => $snapshot->getHeaders(),
            'tableId' => $tableId,
            'page' => $page,
            'amount' => $amount,
            'search' => $search ?? '',
            'totalItems' => $totalItems,
            'allIds' => $model->retrieveAllIds(),
            'listId' => $listId,
            'params' => $params,
            'hasSearch' => count($model->searchTerms()) > 0,
        ]);
    }

    #[Route('/netbs-list/html/{listId}', name: 'netbs.core.netbs_list_html')]
    public function netbsHtmlAction($listId, Request $request, ListManager $listManager, ListEngine $engine): Response {

        $model = $listManager->getModelByAlias($listId);

        [$page, $amount, $search] = $this->extractPaginationFromRequest($request->query);
        $tableId = $this->getOrDefault($request->query->get('tableId'), 'list');
        $params  = $this->decodeParams($request->query->get('params'));

        $this->applyParamsToModel($model, $params);

        $snapshot = $engine->generateSnaphot($model);
        $allRows  = $this->buildRowsWithIds($snapshot, $model);
        $allIds   = array_column($allRows, 'id');

        $filteredRows = $this->filterRowsByTextSearch($allRows, $search);
        $rows         = $this->paginate($filteredRows, $page, $amount);

        return $this->render('@NetBSCore/renderer/ajax.frame.twig', [
            'rows' => $rows,
            'headers' => $snapshot->getHeaders(),
            'tableId' => $tableId,
            'page' => $page,
            'amount' => $amount,
            'search' => $search ?? '',
            'totalItems' => count($filteredRows),
            'allIds' => $allIds,
            'listId' => $listId,
            'params' => $params,
            'hasSearch' => true,
            'baseUrl' => $this->generateUrl('netbs.core.netbs_list_html', ['listId' => $listId]),
        ]);
    }

    private function requireAjaxModel(ListManager $listManager, string $listId): AjaxModel {
        $model = $listManager->getModelByAlias($listId);
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Model $listId must extend the AjaxModel class");
        }
        return $model;
    }

    /**
     * @return array{0:int,1:int,2:?string}
     */
    private function extractPaginationFromRequest(\Symfony\Component\HttpFoundation\InputBag $query): array {
        $amount = intval($this->getOrDefault($query->get('amount'), 10));
        $page   = intval($this->getOrDefault($query->get('page'), 0));
        $search = $this->getOrDefault($query->get('search'), null);
        $search = empty($search) ? null : $search;
        return [$page, $amount, $search];
    }

    private function decodeParams(?string $json): array {
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    private function applyParamsToModel(BaseListModel $model, array $params): void {
        foreach ($params as $key => $value) {
            $model->setParameter($key, $value);
        }
    }

    /**
     * Pair each snapshot row with its backing element id: [['id' => ..., 'cells' => [...]], ...].
     */
    private function buildRowsWithIds(SnapshotTable $snapshot, BaseListModel $model): array {
        $elements = $model->getElements();
        $elements = is_array($elements) ? array_values($elements) : iterator_to_array($elements, false);
        $data     = $snapshot->getData();

        $rows = [];
        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $rows[] = [
                'id'    => $elements[$i]->getId(),
                'cells' => $data[$i],
            ];
        }
        return $rows;
    }

    /**
     * Pair each snapshot row with its element id, flattening cell values into a plain array:
     * [['id' => ..., 'row' => [v1, v2, ...]], ...].
     */
    private function pairElementsWithRows(SnapshotTable $snapshot, BaseListModel $model): array {
        $elements = $model->getElements();
        $data     = $snapshot->getData();

        $res = [];
        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $res[] = [
                'id'  => $elements[$i]->getId(),
                'row' => array_values($data[$i]),
            ];
        }
        return $res;
    }

    private function filterRowsByTextSearch(array $rows, ?string $search): array {
        if (!$search) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($search) {
            foreach ($row['cells'] as $cell) {
                if (stripos(strip_tags((string)$cell), $search) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    private function paginate(array $rows, int $page, int $amount): array {
        return array_slice($rows, $page * $amount, $amount);
    }

    private function getOrDefault($value, $default) {
        return empty($value) ? $default : $value;
    }
}
