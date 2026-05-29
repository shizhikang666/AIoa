<?php

declare(strict_types=1);

namespace app\service\tenant;

use think\facade\Db;

/**
 * Read-only tenant table queries compatible with Java TenantsController.
 */
class TenantsService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'tenantId' => 'Tenant_ID',
        'tenantName' => 'Tenant_Name',
        'code' => 'CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->tenantQuery($filters)->count();
        $rows = $this->applySort($this->tenantQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->tenantRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $tenantId): ?array
    {
        $row = $this->tenantQuery(['tenantId' => $tenantId])->find();
        if (!$row) {
            return null;
        }

        return $this->tenantRow(is_array($row) ? $row : $row->toArray());
    }

    private function tenantQuery(array $filters)
    {
        $query = Db::name('tenants')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('Tenant_ID', (string)$filters['id']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('Tenant_ID', (string)$filters['tenantId']);
        }

        if (!empty($filters['tenantName'])) {
            $query->whereLike('Tenant_Name', '%' . trim((string)$filters['tenantName']) . '%');
        }

        if (!empty($filters['code'])) {
            $query->whereLike('CODE', '%' . trim((string)$filters['code']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $searchKey = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('Tenant_Name', $searchKey)
                    ->whereOr('CODE', 'like', $searchKey);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('Tenant_ID', 'asc');
        }

        return $query->order('Tenant_ID', 'asc');
    }

    private function tenantRow(array $row): array
    {
        return [
            'tenantId' => $row['Tenant_ID'] ?? null,
            'tenantName' => $row['Tenant_Name'] ?? null,
            'code' => $row['CODE'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
