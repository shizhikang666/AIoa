<?php

declare(strict_types=1);

namespace app\service\biz;

use think\facade\Db;

/**
 * Read-only annual-leave balance queries compatible with Java BizUserVacationController.
 */
class BizUserVacationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DEFAULT_CATEGORY = 'annualLeave';
    private const FIELDS = <<<SQL
v.ID AS ID,
v.USER_ID AS USER_ID,
v.AMOUNT AS AMOUNT,
v.USED_AMOUNT AS USED_AMOUNT,
v.CATEGORY AS CATEGORY,
v.DELETE_FLAG AS DELETE_FLAG,
v.CREATE_TIME AS CREATE_TIME,
v.CREATE_USER AS CREATE_USER,
v.UPDATE_TIME AS UPDATE_TIME,
v.UPDATE_USER AS UPDATE_USER,
v.TENANT_ID AS TENANT_ID,
v.VERSION AS VERSION,
u.NAME AS USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'v.ID',
        'userId' => 'v.USER_ID',
        'userName' => 'u.NAME',
        'amount' => 'v.AMOUNT',
        'usedAmount' => 'v.USED_AMOUNT',
        'category' => 'v.CATEGORY',
        'createTime' => 'v.CREATE_TIME',
        'updateTime' => 'v.UPDATE_TIME',
        'tenantId' => 'v.TENANT_ID',
        'version' => 'v.VERSION',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->vacationQuery($filters, $payload)->count();
        $rows = $this->applySort($this->vacationQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->vacationRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(array $filters = [], array $payload = []): array
    {
        $userId = trim((string)($filters['userId'] ?? $filters['user_id'] ?? ''));
        if ($userId === '') {
            $userId = $this->currentUserId($payload);
        }

        $category = trim((string)($filters['category'] ?? ''));
        if ($category === '') {
            $category = self::DEFAULT_CATEGORY;
        }

        $query = Db::name('biz_user_vacation')
            ->alias('v')
            ->leftJoin('sys_user u', 'u.ID = v.USER_ID')
            ->field(self::FIELDS)
            ->where('v.USER_ID', $userId)
            ->where('v.CATEGORY', $category)
            ->where('v.DELETE_FLAG', self::NOT_DELETE)
            ->whereBetweenTime('v.CREATE_TIME', date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59'));

        $tenantId = $this->tenantId($payload);
        if ($tenantId !== '') {
            $query->where('v.TENANT_ID', $tenantId);
        }

        $row = $query->order('v.CREATE_TIME', 'desc')
            ->order('v.ID', 'desc')
            ->find();

        if (!is_array($row) || $row === []) {
            return $this->emptyAnnualLeaveRow($userId, $category);
        }

        return $this->vacationRow($row);
    }

    private function vacationQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_user_vacation')
            ->alias('v')
            ->leftJoin('sys_user u', 'u.ID = v.USER_ID')
            ->where('v.DELETE_FLAG', self::NOT_DELETE);

        $tenantId = trim((string)($filters['tenantId'] ?? $filters['tenant_id'] ?? $this->tenantId($payload)));
        if ($tenantId !== '') {
            $query->where('v.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'v.ID',
            'userId' => 'v.USER_ID',
            'category' => 'v.CATEGORY',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['userName'])) {
            $query->whereLike('u.NAME', '%' . trim((string)$filters['userName']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('v.USER_ID', 'like', $keyword)
                    ->whereOr('v.CATEGORY', 'like', $keyword);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('v.ID', 'asc');
        }

        return $query->order('v.ID', 'asc');
    }

    private function vacationRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'userId' => $row['USER_ID'] ?? null,
            'userName' => $row['USER_NAME'] ?? null,
            'amount' => $this->decimal($row['AMOUNT'] ?? 0),
            'usedAmount' => $this->decimal($row['USED_AMOUNT'] ?? 0),
            'category' => $row['CATEGORY'] ?? self::DEFAULT_CATEGORY,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'version' => (int)($row['VERSION'] ?? 0),
        ]);
    }

    private function emptyAnnualLeaveRow(string $userId, string $category): array
    {
        return [
            'id' => null,
            'userId' => $userId !== '' ? $userId : null,
            'userName' => null,
            'amount' => '0',
            'usedAmount' => '0',
            'category' => $category !== '' ? $category : self::DEFAULT_CATEGORY,
            'deleteFlag' => self::NOT_DELETE,
            'createTime' => null,
            'createUser' => null,
            'updateTime' => null,
            'updateUser' => null,
            'tenantId' => null,
            'version' => 0,
        ];
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

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}
