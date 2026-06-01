<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysOrg;

/**
 * Read-only organization queries compatible with Java SysOrgService.
 */
class OrgService
{
    private const NOT_DELETE = 'NOT_DELETE';

    public function __construct(private readonly TreeBuilder $treeBuilder = new TreeBuilder())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return array_map(
            fn (array $row): array => $this->orgRow($row),
            $this->rawRows($filters)
        );
    }

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $records = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->orgRow($row), $records),
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
    public function tree(array $filters = []): array
    {
        return $this->normalizeTree($this->treeBuilder->build($this->rawRows($filters)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function selector(array $filters = []): array
    {
        return $this->treeBuilder->toSelector($this->treeBuilder->build($this->rawRows($filters)));
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $this->orgRow($row->toArray()) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawRows(array $filters = []): array
    {
        return $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
    }

    private function baseQuery(array $filters)
    {
        $query = SysOrg::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $query->where('PARENT_ID', (string)$filters['parentId']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function orgRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'directorId' => $row['DIRECTOR_ID'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTree(array $nodes): array
    {
        return array_map(function (array $node): array {
            $children = $node['children'] ?? [];
            $row = $this->orgRow($node);
            $row['children'] = is_array($children) ? $this->normalizeTree($children) : [];

            return $row;
        }, $nodes);
    }
}
