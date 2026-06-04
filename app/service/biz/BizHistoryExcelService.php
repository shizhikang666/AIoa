<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only history Excel queries compatible with Java BizHistoryExcelController.
 */
class BizHistoryExcelService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const FIELDS = <<<SQL
h.ID AS ID,
h.NAME AS NAME,
h.REMARK AS REMARK,
h.DELETE_FLAG AS DELETE_FLAG,
h.EXT_JSON AS EXT_JSON,
h.CREATE_TIME AS CREATE_TIME,
h.CREATE_USER AS CREATE_USER,
h.UPDATE_TIME AS UPDATE_TIME,
h.UPDATE_USER AS UPDATE_USER,
h.TENANT_ID AS TENANT_ID
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'h.ID',
        'name' => 'h.NAME',
        'createTime' => 'h.CREATE_TIME',
        'createUser' => 'h.CREATE_USER',
        'updateTime' => 'h.UPDATE_TIME',
        'updateUser' => 'h.UPDATE_USER',
        'deleteFlag' => 'h.DELETE_FLAG',
        'tenantId' => 'h.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->historyQuery($filters, $payload)->count();
        $rows = $this->applySort($this->historyQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->historyRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->historyQuery(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('history excel not found', 404);
        }

        return $this->historyRow($row);
    }

    private function historyQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_history_excel')
            ->alias('h')
            ->where(function ($query): void {
                $query->whereNull('h.DELETE_FLAG')->whereOr('h.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $id = trim((string)($filters['id'] ?? ''));
        if ($id !== '') {
            $query->where('h.ID', $id);
        }

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('h.TENANT_ID', $tenantId);
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('h.ID', 'asc');
        }

        return $query->order('h.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->historyRow($row), $rows);
    }

    private function historyRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'remark' => $row['REMARK'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(100, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
