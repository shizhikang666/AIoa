<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only leave-application queries compatible with Java BizLeaveApplicationController.
 */
class BizLeaveApplicationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const FIELDS = <<<SQL
l.ID AS ID,
l.USER_ID AS USER_ID,
l.PROCESS_ID AS PROCESS_ID,
l.`category` AS CATEGORY,
l.AMOUNT AS AMOUNT,
l.REMARK AS REMARK,
l.START_TIME AS START_TIME,
l.END_TIME AS END_TIME,
l.DELETE_FLAG AS DELETE_FLAG,
l.CREATE_TIME AS CREATE_TIME,
l.CREATE_USER AS CREATE_USER,
l.UPDATE_TIME AS UPDATE_TIME,
l.UPDATE_USER AS UPDATE_USER,
l.TENANT_ID AS TENANT_ID,
l.OBJECT_ID AS OBJECT_ID,
u.NAME AS NAME,
u.ORG_ID AS ORG_ID,
org.NAME AS ORG_NAME,
creator.NAME AS CREATE_USER_NAME,
updater.NAME AS UPDATE_USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'l.ID',
        'userId' => 'l.USER_ID',
        'name' => 'u.NAME',
        'orgId' => 'u.ORG_ID',
        'orgName' => 'org.NAME',
        'processId' => 'l.PROCESS_ID',
        'category' => 'l.category',
        'amount' => 'l.AMOUNT',
        'remark' => 'l.REMARK',
        'startTime' => 'l.START_TIME',
        'endTime' => 'l.END_TIME',
        'createTime' => 'l.CREATE_TIME',
        'updateTime' => 'l.UPDATE_TIME',
        'objectId' => 'l.OBJECT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->leaveQuery($filters, $payload, false)->count();
        $rows = $this->applySort($this->leaveQuery($filters, $payload, false), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->leaveRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function myPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->leaveQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->leaveQuery($filters, $payload, true), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->leaveRows($rows),
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
        $row = $this->leaveQuery(['id' => $id], $payload, false)
            ->field(self::FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('leave application not found', 404);
        }

        return $this->leaveRows([$row])[0];
    }

    private function leaveQuery(array $filters, array $payload, bool $onlyCurrentUser)
    {
        $query = Db::name('biz_leave_application')
            ->alias('l')
            ->leftJoin('sys_user u', 'u.ID = l.USER_ID')
            ->leftJoin('sys_org org', 'org.ID = u.ORG_ID')
            ->leftJoin('sys_user creator', 'creator.ID = l.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = l.UPDATE_USER')
            ->where(function ($query): void {
                $query->whereNull('l.DELETE_FLAG')->whereOr('l.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('l.TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('l.ID', (string)$filters['id']);
        }

        if ($onlyCurrentUser) {
            $userId = $this->currentUserId($payload);
            $userId === '' ? $query->whereRaw('1 = 0') : $query->where('l.USER_ID', $userId);
        } else {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('u.ORG_ID', $scopeOrgIds);
            } else {
                $userId = $this->currentUserId($payload);
                $userId === '' ? $query->whereRaw('1 = 0') : $query->where('l.USER_ID', $userId);
            }
        }

        if (!empty($filters['userId'])) {
            $query->where('l.USER_ID', (string)$filters['userId']);
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('u.ORG_ID', $orgIds);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('u.NAME', '%' . trim((string)$filters['name']) . '%');
        }

        $categories = $this->listParam($filters['category'] ?? []);
        if ($categories !== []) {
            $query->whereIn('l.category', $categories);
        }

        if (!empty($filters['amount'])) {
            $query->whereLike('l.AMOUNT', '%' . trim((string)$filters['amount']) . '%');
        }

        if (!empty($filters['remark'])) {
            $query->whereLike('l.REMARK', '%' . trim((string)$filters['remark']) . '%');
        }

        if (!empty($filters['processId'])) {
            $query->where('l.PROCESS_ID', (string)$filters['processId']);
        }

        if (!empty($filters['objectId'])) {
            $query->where('l.OBJECT_ID', (string)$filters['objectId']);
        }

        $this->applyTimeRange($query, $filters, 'l.START_TIME', 'startStartTime', 'endStartTime');
        $this->applyTimeRange($query, $filters, 'l.END_TIME', 'startEndTime', 'endEndTime');

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('u.NAME', $keyword)
                    ->whereOr('l.REMARK', 'like', $keyword)
                    ->whereOr('l.PROCESS_ID', 'like', $keyword)
                    ->whereOr('l.OBJECT_ID', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('l.ID', 'asc');
        }

        return $query->order('l.ID', 'asc');
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function leaveRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->leaveRow($row), $rows);
    }

    private function leaveRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'userId' => $this->value($row, 'USER_ID', 'userId'),
            'name' => $this->value($row, 'NAME', 'name'),
            'orgId' => $this->value($row, 'ORG_ID', 'orgId'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'category' => $this->value($row, 'CATEGORY', 'category'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'startTime' => $this->value($row, 'START_TIME', 'startTime'),
            'endTime' => $this->value($row, 'END_TIME', 'endTime'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'updateUserName' => $this->value($row, 'UPDATE_USER_NAME', 'updateUserName'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @return array<int, string>
     */
    private function listParam(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = explode(',', $value);
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string)$item), $value))));
    }

    /**
     * @return array<int, string>
     */
    private function orgAndChildren(string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $rows = Db::name('sys_org')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
        }

        $result = [];
        $queue = [$orgId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct) && $direct !== []) {
            return array_values(array_unique(array_filter(array_map('strval', $direct))));
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static function (mixed $scope): string {
            if (!is_array($scope)) {
                return '';
            }

            return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
        }, $scopes))));
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
