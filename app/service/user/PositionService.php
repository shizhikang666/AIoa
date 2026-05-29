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
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $row->toArray() : null;
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
}
