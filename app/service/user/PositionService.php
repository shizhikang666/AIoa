<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysPosition;

/**
 * Read-only position queries compatible with Java SysPositionService.
 */
class PositionService
{
    private const NOT_DELETE = 'NOT_DELETE';

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
            'records' => array_map(fn (array $row): array => $this->positionRow($row), $records),
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
    public function all(array $filters = []): array
    {
        $rows = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->positionRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $this->positionRow($row->toArray()) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function selector(array $filters = []): array
    {
        return array_map(static function (array $row): array {
            return [
                'id' => $row['ID'] ?? null,
                'orgId' => $row['ORG_ID'] ?? null,
                'value' => $row['ID'] ?? null,
                'label' => $row['NAME'] ?? null,
                'title' => $row['NAME'] ?? null,
            ];
        }, $this->all($filters));
    }

    private function baseQuery(array $filters)
    {
        $query = SysPosition::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
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

    private function positionRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }
}
