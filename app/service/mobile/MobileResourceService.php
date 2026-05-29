<?php

declare(strict_types=1);

namespace app\service\mobile;

use app\model\MobileResource;

/**
 * Read-only mobile resource queries compatible with Java MobileModule/Menu/Button controllers.
 */
class MobileResourceService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const CATEGORY_MODULE = 'MODULE';
    private const CATEGORY_MENU = 'MENU';
    private const CATEGORY_BUTTON = 'BUTTON';
    private const ROOT_PARENT_ID = '0';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'title' => 'TITLE',
        'code' => 'CODE',
        'category' => 'CATEGORY',
        'module' => 'MODULE',
        'parentId' => 'PARENT_ID',
        'status' => 'STATUS',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function modulePage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_MODULE, $filters);
    }

    public function buttonPage(array $filters = []): array
    {
        return $this->page(self::CATEGORY_BUTTON, $filters);
    }

    public function detail(string $id, string $category): ?array
    {
        $row = $this->resourceQuery($category, ['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->resourceRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menuTree(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MENU, $filters, false)
            ->order('SORT_CODE', 'desc')
            ->order('ID', 'asc')
            ->select()
            ->toArray();

        return $this->buildTree($rows, false, 'desc');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function moduleSelector(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MODULE, $filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->selectorRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menuTreeSelector(array $filters = []): array
    {
        $rows = $this->resourceQuery(self::CATEGORY_MENU, $filters, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows, true);
    }

    private function page(string $category, array $filters): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->resourceQuery($category, $filters)->count();
        $rows = $this->applySort($this->resourceQuery($category, $filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->resourceRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function resourceQuery(string $category, array $filters = [], bool $moduleLike = true)
    {
        $query = MobileResource::where('CATEGORY', $category)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $query->where('PARENT_ID', (string)$filters['parentId']);
        }

        if (!empty($filters['module'])) {
            $module = trim((string)$filters['module']);
            $moduleLike ? $query->whereLike('MODULE', '%' . $module . '%') : $query->where('MODULE', $module);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('TITLE', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['title'])) {
            $query->whereLike('TITLE', '%' . trim((string)$filters['title']) . '%');
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order(['SORT_CODE' => 'asc', 'ID' => 'asc']);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $rows, bool $selector = false, string $sortDirection = 'asc'): array
    {
        $rows = $this->sortRows($rows, $sortDirection);
        $nodes = [];
        $parentById = [];

        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            if ($id === '') {
                continue;
            }

            $node = $selector ? $this->selectorRow($row) : $this->treeRow($row);
            $node['children'] = [];
            $nodes[$id] = $node;
            $parentById[$id] = (string)($row['PARENT_ID'] ?? self::ROOT_PARENT_ID);
        }

        $tree = [];
        foreach ($nodes as $id => &$node) {
            $parentId = $parentById[$id] ?? self::ROOT_PARENT_ID;
            if ($parentId !== self::ROOT_PARENT_ID && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
                continue;
            }

            $tree[] = &$node;
        }
        unset($node);

        return $tree;
    }

    private function treeRow(array $row): array
    {
        $resource = $this->resourceRow($row);
        $resource['label'] = $resource['title'];
        $resource['value'] = $resource['id'];
        $resource['weight'] = $resource['sortCode'];

        return $resource;
    }

    private function selectorRow(array $row): array
    {
        $resource = $this->resourceRow($row);
        $resource['label'] = $resource['title'];
        $resource['value'] = $resource['id'];
        $resource['name'] = $resource['title'];
        $resource['weight'] = $resource['sortCode'];

        return $resource;
    }

    private function resourceRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'title' => $row['TITLE'] ?? null,
            'code' => $row['CODE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'module' => $row['MODULE'] ?? null,
            'menuType' => $row['MENU_TYPE'] ?? null,
            'path' => $row['PATH'] ?? null,
            'icon' => $row['ICON'] ?? null,
            'color' => $row['COLOR'] ?? null,
            'regType' => $row['REG_TYPE'] ?? null,
            'status' => $row['STATUS'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $direction = 'asc'): array
    {
        usort($rows, static function (array $left, array $right) use ($direction): int {
            $leftSort = (int)($left['SORT_CODE'] ?? 0);
            $rightSort = (int)($right['SORT_CODE'] ?? 0);
            if ($leftSort !== $rightSort) {
                return $direction === 'desc' ? $rightSort <=> $leftSort : $leftSort <=> $rightSort;
            }

            return strcmp((string)($left['ID'] ?? ''), (string)($right['ID'] ?? ''));
        });

        return $rows;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
