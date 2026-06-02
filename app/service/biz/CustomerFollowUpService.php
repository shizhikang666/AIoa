<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only customer follow-up queries compatible with Java CustomerFollowUpController.
 */
class CustomerFollowUpService
{
    private const NOT_DELETE = 'NOT_DELETE';

    private const FOLLOW_UP_FIELDS = <<<SQL
f.ID AS ID,
f.CUSTOMER_ID AS CUSTOMER_ID,
f.FOLLOW_UP_TIME AS FOLLOW_UP_TIME,
f.CONTENT AS CONTENT,
f.DELETE_FLAG AS DELETE_FLAG,
f.CREATE_TIME AS CREATE_TIME,
f.CREATE_USER AS CREATE_USER,
f.UPDATE_TIME AS UPDATE_TIME,
f.UPDATE_USER AS UPDATE_USER,
f.TENANT_ID AS TENANT_ID,
f.EXT_JSON AS EXT_JSON,
c.NAME AS CUSTOMER_NAME,
creator.NAME AS CREATE_USER_NAME,
creator.AVATAR AS AVATAR,
creator.ORG_ID AS CREATE_USER_ORG_ID,
creatorOrg.NAME AS CREATE_USER_ORG_NAME
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'f.ID',
        'customerId' => 'f.CUSTOMER_ID',
        'customerName' => 'c.NAME',
        'followUpTime' => 'f.FOLLOW_UP_TIME',
        'createTime' => 'f.CREATE_TIME',
        'createUserName' => 'creator.NAME',
        'tenantId' => 'f.TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->followUpQuery($filters, $payload, true)->count('DISTINCT f.ID');
        $rows = $this->applySort($this->followUpQuery($filters, $payload, true), $filters)
            ->field(self::FOLLOW_UP_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->followUpRows($rows),
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
        $row = $this->followUpQuery(['id' => $id], $payload, true)
            ->field(self::FOLLOW_UP_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('customer follow-up not found', 404);
        }

        return $this->followUpRows([$row])[0];
    }

    /**
     * @param array<int, string> $customerIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function listByCustomerIds(array $customerIds, array $payload = []): array
    {
        $ids = $this->stringList($customerIds);
        if ($ids === []) {
            return [];
        }

        $query = $this->baseQuery($payload)
            ->whereIn('f.CUSTOMER_ID', $ids)
            ->field(self::FOLLOW_UP_FIELDS)
            ->order('f.FOLLOW_UP_TIME', 'asc')
            ->order('f.ID', 'asc');

        $result = [];
        foreach ($this->followUpRows($query->select()->toArray()) as $row) {
            $result[(string)$row['customerId']][] = $row;
        }

        return $result;
    }

    private function followUpQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = $this->baseQuery($payload);

        if (!empty($filters['id'])) {
            $query->where('f.ID', (string)$filters['id']);
        }

        if (!empty($filters['customerId'])) {
            $query->where('f.CUSTOMER_ID', (string)$filters['customerId']);
        }

        foreach ([
            'content' => 'f.CONTENT',
            'customerName' => 'c.NAME',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        $this->applyTimeRange($query, 'f.FOLLOW_UP_TIME', $filters['startFollowUpTime'] ?? '', $filters['endFollowUpTime'] ?? '');

        if ($applyDataScope) {
            $this->applyDataScope($query, $filters, $payload);
        }

        return $query;
    }

    private function baseQuery(array $payload)
    {
        $query = Db::name('customer_follow_up')
            ->alias('f')
            ->leftJoin('customer c', 'c.ID = f.CUSTOMER_ID')
            ->leftJoin('sys_user creator', 'creator.ID = f.CREATE_USER')
            ->leftJoin('sys_org creatorOrg', 'creatorOrg.ID = creator.ORG_ID');
        $this->whereNotDeleted($query, 'f.DELETE_FLAG');
        $this->whereNotDeleted($query, 'c.DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('f.TENANT_ID', $tenantId);
        }

        return $query;
    }

    private function applyDataScope($query, array $filters, array $payload): void
    {
        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            $orgIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('c.ORG', $orgIds);

            return;
        }

        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== []) {
            $query->whereIn('c.ORG', $scopeOrgIds);

            return;
        }

        $userId = $this->currentUserId($payload);
        if ($userId !== '') {
            $query->where('c.USER', $userId);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('f.ID', 'asc');
        }

        return $query->order('f.ID', 'asc');
    }

    private function applyTimeRange($query, string $column, mixed $startValue, mixed $endValue): void
    {
        $start = trim((string)$startValue);
        $end = trim((string)$endValue);
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        } elseif ($start !== '') {
            $query->whereTime($column, '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime($column, '<=', $end);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function followUpRows(array $rows): array
    {
        return array_map(fn (array $row): array => [
            'id' => $this->value($row, 'ID', 'id'),
            'customerId' => $this->value($row, 'CUSTOMER_ID', 'customerId'),
            'customerName' => $this->value($row, 'CUSTOMER_NAME', 'customerName'),
            'followUpTime' => $this->value($row, 'FOLLOW_UP_TIME', 'followUpTime'),
            'content' => $this->value($row, 'CONTENT', 'content'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'avatar' => $this->value($row, 'AVATAR', 'avatar'),
            'createUserOrgId' => $this->value($row, 'CREATE_USER_ORG_ID', 'createUserOrgId'),
            'createUserOrgName' => $this->value($row, 'CREATE_USER_ORG_NAME', 'createUserOrgName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
        ], $rows);
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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
        $ids = [];
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                if (is_array($scope)) {
                    $ids[] = $scope['orgId'] ?? $scope['org_id'] ?? '';
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (in_array(strtolower((string)$roleCode), ['superadmin', 'tenantadmin', 'bizadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)$item), $value)));
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
