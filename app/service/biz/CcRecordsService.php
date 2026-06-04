<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only CC record queries compatible with Java BizCcRecordsController.
 */
class CcRecordsService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const SORT_FIELD_MAP = [
        'id' => 'c.ID',
        'title' => 'c.TITLE',
        'processId' => 'c.PROCESS_ID',
        'promoterId' => 'c.PROMOTER_ID',
        'instanceId' => 'c.INSTANCE_ID',
        'category' => 'c.CATEGORY',
        'user' => 'c.USER',
        'createTime' => 'c.CREATE_TIME',
        'updateTime' => 'c.UPDATE_TIME',
        'tenantId' => 'c.TENANT_ID',
    ];
    private const FIELDS = <<<SQL
c.ID AS ID,
c.TITLE AS TITLE,
c.PROCESS_ID AS PROCESS_ID,
c.PROMOTER_ID AS PROMOTER_ID,
c.INSTANCE_ID AS INSTANCE_ID,
c.CATEGORY AS CATEGORY,
c.EXT_JSON AS EXT_JSON,
c.USER AS USER_ID,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.TENANT_ID AS TENANT_ID,
promoter.NAME AS PROMOTER_NAME,
receiver.NAME AS USER_NAME
SQL;

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters, $payload)->count();
        $rows = $this->applySort($this->baseQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->recordRow($row), $rows),
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
        $row = $this->baseQuery(['id' => $id], $payload)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('cc record not found', 404);
        }

        return $this->recordRow($row);
    }

    private function baseQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_cc_records')
            ->alias('c')
            ->leftJoin('sys_user promoter', 'promoter.ID = c.PROMOTER_ID')
            ->leftJoin('sys_user receiver', 'receiver.ID = c.USER')
            ->where('c.DELETE_FLAG', self::NOT_DELETE);

        $currentUserId = $this->currentUserId($payload);
        if ($currentUserId !== '') {
            $query->where('c.USER', $currentUserId);
        }

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('c.ID', (string)$filters['id']);
        }

        if (!empty($filters['title'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['title']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('c.TITLE', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['processId'])) {
            $query->where('c.PROCESS_ID', (string)$filters['processId']);
        }

        if (!empty($filters['promoterId'])) {
            $query->where('c.PROMOTER_ID', (string)$filters['promoterId']);
        }

        if (!empty($filters['instanceId'])) {
            $query->where('c.INSTANCE_ID', (string)$filters['instanceId']);
        }

        if (!empty($filters['category'])) {
            $query->where('c.CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['startCreateTime']) && !empty($filters['endCreateTime'])) {
            $query->whereBetweenTime(
                'c.CREATE_TIME',
                (string)$filters['startCreateTime'],
                (string)$filters['endCreateTime']
            );
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['asc', 'ascend', 'ascending'], true) ? 'asc' : 'desc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('c.ID', 'asc');
        }

        return $query->order('c.ID', 'asc');
    }

    private function recordRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'title' => $row['TITLE'] ?? null,
            'processId' => $row['PROCESS_ID'] ?? null,
            'promoterId' => $row['PROMOTER_ID'] ?? null,
            'promoterName' => $row['PROMOTER_NAME'] ?? null,
            'instanceId' => $row['INSTANCE_ID'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'user' => $row['USER_ID'] ?? null,
            'userName' => $row['USER_NAME'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    private function currentUserId(array $payload): string
    {
        return (string)($payload['userId'] ?? $payload['user_id'] ?? $payload['id'] ?? '');
    }

    private function tenantId(array $payload): string
    {
        return (string)($payload['tenantId'] ?? $payload['tenant_id'] ?? '');
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
