<?php

declare(strict_types=1);

namespace app\service\dev;

use think\facade\Db;

/**
 * Read-only dictionary queries compatible with Java DevDictController.
 */
class DictService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ROOT_PARENT_ID = '0';
    private const SYSTEM_CATEGORY = 'FRM';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'parentId' => 'PARENT_ID',
        'dictLabel' => 'DICT_LABEL',
        'dictValue' => 'DICT_VALUE',
        'category' => 'CATEGORY',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = [], ?string $tenantId = null): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->dictQuery($filters, $tenantId, true)->count();
        $rows = $this->applySort($this->dictQuery($filters, $tenantId, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->dictRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], ?string $tenantId = null): array
    {
        $rows = $this->dictQuery($filters, $tenantId, true)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->dictRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(array $filters = [], ?string $tenantId = null): array
    {
        $rows = $this->dictQuery($filters, $tenantId, true)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function treeAll(array $filters = []): array
    {
        $rows = $this->dictQuery($filters, null, false)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    public function detail(string $id, ?string $tenantId = null): ?array
    {
        $row = $this->dictQuery(['id' => $id], $tenantId, true)->find();
        if (!$row) {
            return null;
        }

        return $this->dictRow(is_array($row) ? $row : $row->toArray());
    }

    private function dictQuery(array $filters, ?string $tenantId, bool $tenantScoped)
    {
        $query = Db::name('dev_dict')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $parentId = (string)$filters['parentId'];
            $query->where(function ($query) use ($parentId): void {
                $query->where('PARENT_ID', $parentId)->whereOr('ID', '=', $parentId);
            });
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('DICT_LABEL', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if ($tenantScoped) {
            $query->where(function ($query) use ($tenantId): void {
                $query->where('CATEGORY', self::SYSTEM_CATEGORY);
                if ($tenantId !== null && $tenantId !== '') {
                    $query->whereOr('TENANT_ID', '=', $tenantId);
                }
            });
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
    private function buildTree(array $rows): array
    {
        $nodes = [];
        $parentById = [];

        foreach ($rows as $row) {
            $id = (string)($row['ID'] ?? '');
            if ($id === '') {
                continue;
            }

            $node = $this->treeRow($row);
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
        $dict = $this->dictRow($row);
        $dict['name'] = $dict['dictLabel'];
        $dict['label'] = $dict['dictLabel'];
        $dict['value'] = $dict['dictValue'];
        $dict['weight'] = $dict['sortCode'];

        return $dict;
    }

    private function dictRow(array $row): array
    {
        return [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'dictLabel' => $row['DICT_LABEL'] ?? null,
            'dictValue' => $row['DICT_VALUE'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'viewState' => $row['VIEW_STATE'] ?? null,
            'editState' => $row['EDIT_STATE'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(500, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
