<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only sale-project field change log queries compatible with Java.
 */
class SalesProjectFieldChangeLogService
{
    private const NOT_DELETE = 'NOT_DELETE';

    private const FIELDS = <<<SQL
l.ID AS ID,
l.OBJECT_ID AS OBJECT_ID,
l.FIELD_NAME AS FIELD_NAME,
l.FIELD_LABEL AS FIELD_LABEL,
l.BEFORE_VALUE AS BEFORE_VALUE,
l.AFTER_VALUE AS AFTER_VALUE,
l.CHANGE_REASON AS CHANGE_REASON,
l.DELETE_FLAG AS DELETE_FLAG,
l.CREATE_TIME AS CREATE_TIME,
l.CREATE_USER AS CREATE_USER,
l.UPDATE_TIME AS UPDATE_TIME,
l.UPDATE_USER AS UPDATE_USER,
l.TENANT_ID AS TENANT_ID,
p.PROJECT_NAME AS PROJECT_NAME,
u.NAME AS CREATE_USER_NAME
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'l.ID',
        'objectId' => 'l.OBJECT_ID',
        'fieldName' => 'l.FIELD_NAME',
        'fieldLabel' => 'l.FIELD_LABEL',
        'createTime' => 'l.CREATE_TIME',
        'updateTime' => 'l.UPDATE_TIME',
        'tenantId' => 'l.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->query($filters, $payload)->count('DISTINCT l.ID');
        $rows = $this->applySort($this->query($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return $this->pageResponse($this->rows($rows), $total, $page, $limit);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->query(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('sales project field change log not found', 404);
        }

        return $this->row($row);
    }

    private function query(array $filters, array $payload)
    {
        $query = Db::name('sales_project_field_change_log')
            ->alias('l')
            ->leftJoin('biz_sale_project p', 'p.ID = l.OBJECT_ID COLLATE utf8mb4_general_ci')
            ->leftJoin('sys_user u', 'u.ID = l.CREATE_USER');
        $this->whereNotDeleted($query, 'l.DELETE_FLAG');
        $this->applyTenant($query, 'l', $filters, $payload);

        foreach ([
            'id' => 'l.ID',
            'objectId' => 'l.OBJECT_ID',
            'fieldName' => 'l.FIELD_NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'fieldLabel' => 'l.FIELD_LABEL',
            'changeReason' => 'l.CHANGE_REASON',
            'projectName' => 'p.PROJECT_NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->row($row), $rows);
    }

    private function row(array $row): array
    {
        return array_merge($this->normalizeRow($row), [
            'id' => $row['ID'] ?? null,
            'objectId' => $row['OBJECT_ID'] ?? null,
            'fieldName' => $row['FIELD_NAME'] ?? null,
            'fieldLabel' => $row['FIELD_LABEL'] ?? null,
            'beforeValue' => $row['BEFORE_VALUE'] ?? null,
            'afterValue' => $row['AFTER_VALUE'] ?? null,
            'changeReason' => $row['CHANGE_REASON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'createUserName' => $row['CREATE_USER_NAME'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'projectName' => $row['PROJECT_NAME'] ?? null,
        ]);
    }

    private function pageResponse(array $records, int $total, int $page, int $limit): array
    {
        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function applyTenant($query, string $alias, array $filters, array $payload): void
    {
        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where("{$alias}.TENANT_ID", $tenantId);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('l.ID', 'asc');
        }

        return $query->order('l.ID', 'asc');
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function normalizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            $result[$this->camelKey((string)$key)] = $value;
        }

        return $result;
    }

    private function camelKey(string $key): string
    {
        $key = strtolower($key);

        return preg_replace_callback('/_([a-z0-9])/', static fn (array $matches): string => strtoupper($matches[1]), $key) ?? $key;
    }
}
