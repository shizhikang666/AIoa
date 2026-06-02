<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only customer queries compatible with Java CustomerController.
 */
class CustomerService
{
    private const NOT_DELETE = 'NOT_DELETE';

    private const CUSTOMER_FIELDS = <<<SQL
c.ID AS ID,
c.NAME AS NAME,
c.CONTACTS AS CONTACTS,
c.PHONE AS PHONE,
c.DETAILS_ADDRESS AS DETAILS_ADDRESS,
c.ADDRESS AS ADDRESS,
c.SOURCE_TYPE AS SOURCE_TYPE,
c.CUSTOM_TYPE AS CUSTOM_TYPE,
c.ORG AS ORG,
c.USER AS USER_ID,
c.STATUS AS STATUS,
c.SORT_CODE AS SORT_CODE,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.EXT_JSON AS EXT_JSON,
c.TENANT_ID AS TENANT_ID,
c.FILE_ID AS FILE_ID,
c.VERSION AS VERSION,
c.DEAL_AMOUNT AS DEAL_AMOUNT,
c.remark AS REMARK,
c.FIRST_CONTACT_TIME AS FIRST_CONTACT_TIME,
head.NAME AS HEAD_NAME,
org.NAME AS ORG_NAME,
creator.NAME AS CREATE_USER_NAME,
df.DOWNLOAD_PATH AS DOWNLOAD_PATH
SQL;

    private const SORT_FIELD_MAP = [
        'id' => 'c.ID',
        'name' => 'c.NAME',
        'contacts' => 'c.CONTACTS',
        'phone' => 'c.PHONE',
        'address' => 'c.ADDRESS',
        'detailsAddress' => 'c.DETAILS_ADDRESS',
        'sourceType' => 'c.SOURCE_TYPE',
        'customType' => 'c.CUSTOM_TYPE',
        'org' => 'c.ORG',
        'orgName' => 'org.NAME',
        'user' => 'c.USER',
        'headName' => 'head.NAME',
        'status' => 'c.STATUS',
        'sortCode' => 'c.SORT_CODE',
        'createTime' => 'c.CREATE_TIME',
        'createUserName' => 'creator.NAME',
        'firstContactTime' => 'c.FIRST_CONTACT_TIME',
        'tenantId' => 'c.TENANT_ID',
    ];

    public function __construct(private readonly CustomerFollowUpService $followUpService = new CustomerFollowUpService())
    {
    }

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = (int)$this->customerQuery($filters, $payload, true)->count('DISTINCT c.ID');
        $rows = $this->applySort($this->customerQuery($filters, $payload, true), $filters)
            ->field(self::CUSTOMER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->customerRows($rows),
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
        $row = $this->customerQuery(['id' => $id], $payload, true)
            ->field(self::CUSTOMER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('customer not found', 404);
        }

        return $this->customerRows([$row])[0];
    }

    /**
     * @return array<int, array{customer: array<string, mixed>, customerFollowUps: array<int, array<string, mixed>>}>
     */
    public function detailList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->customerQuery($filters, $payload, true), $filters)
            ->field(self::CUSTOMER_FIELDS)
            ->select()
            ->toArray();
        $customers = $this->customerRows($rows);
        $followUps = $this->followUpService->listByCustomerIds(array_column($customers, 'id'), $payload);

        return array_map(static fn (array $customer): array => [
            'customer' => $customer,
            'customerFollowUps' => $followUps[(string)$customer['id']] ?? [],
        ], $customers);
    }

    private function customerQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('customer')
            ->alias('c')
            ->leftJoin('sys_user head', 'head.ID = c.USER')
            ->leftJoin('sys_org org', 'org.ID = c.ORG')
            ->leftJoin('sys_user creator', 'creator.ID = c.CREATE_USER')
            ->leftJoin('dev_file df', 'df.ID = c.FILE_ID');
        $this->whereNotDeleted($query, 'c.DELETE_FLAG');

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'c.ID',
            'status' => 'c.STATUS',
            'sourceType' => 'c.SOURCE_TYPE',
            'customType' => 'c.CUSTOM_TYPE',
            'org' => 'c.ORG',
            'user' => 'c.USER',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        foreach ([
            'name' => 'c.NAME',
            'contacts' => 'c.CONTACTS',
            'phone' => 'c.PHONE',
            'detailsAddress' => 'c.DETAILS_ADDRESS',
            'address' => 'c.ADDRESS',
            'remark' => 'c.remark',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['headName'])) {
            $this->applyUserNameFilter($query, (string)$filters['headName'], 'c.USER');
        }

        if (!empty($filters['createUserName'])) {
            $this->applyUserNameFilter($query, (string)$filters['createUserName'], 'c.CREATE_USER');
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                '(c.NAME LIKE ? OR c.CONTACTS LIKE ? OR c.ADDRESS LIKE ? OR head.NAME LIKE ? OR creator.NAME LIKE ?)',
                [$keyword, $keyword, $keyword, $keyword, $keyword]
            );
        }

        if ($this->truthy($filters['showRepeat'] ?? false)) {
            $query->whereRaw(
                'c.PHONE IN (SELECT PHONE FROM customer WHERE PHONE IS NOT NULL AND (DELETE_FLAG IS NULL OR DELETE_FLAG = ?) GROUP BY PHONE HAVING COUNT(*) > 1)',
                [self::NOT_DELETE]
            );
        }

        $this->applyTimeRange($query, 'c.CREATE_TIME', $filters['startCreateTime'] ?? '', $filters['endCreateTime'] ?? '');
        $this->applyTimeRange($query, 'c.FIRST_CONTACT_TIME', $filters['startFirstContactTime'] ?? '', $filters['endFirstContactTime'] ?? '');

        if ($applyDataScope) {
            $this->applyDataScope($query, $filters, $payload);
        }

        return $query;
    }

    private function applyUserNameFilter($query, string $name, string $targetColumn): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        $userIds = Db::name('sys_user')
            ->whereLike('NAME', '%' . $name . '%')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->column('ID');

        if ($userIds !== []) {
            $query->whereIn($targetColumn, array_map('strval', $userIds));

            return;
        }

        $query->where($targetColumn, $name);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('c.ID', 'asc');
        }

        return $query->order('c.SORT_CODE', 'asc')->order('c.ID', 'asc');
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
    private function customerRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'name' => $this->value($row, 'NAME', 'name'),
                'contacts' => $this->value($row, 'CONTACTS', 'contacts'),
                'phone' => $this->value($row, 'PHONE', 'phone'),
                'detailsAddress' => $this->value($row, 'DETAILS_ADDRESS', 'detailsAddress'),
                'address' => $this->value($row, 'ADDRESS', 'address'),
                'sourceType' => $this->value($row, 'SOURCE_TYPE', 'sourceType'),
                'customType' => $this->value($row, 'CUSTOM_TYPE', 'customType'),
                'org' => $this->value($row, 'ORG', 'org'),
                'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
                'user' => $this->value($row, 'USER_ID', 'user'),
                'headName' => $this->value($row, 'HEAD_NAME', 'headName'),
                'status' => $this->value($row, 'STATUS', 'status'),
                'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'fileId' => $this->value($row, 'FILE_ID', 'fileId'),
                'downloadPath' => $this->value($row, 'DOWNLOAD_PATH', 'downloadPath'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'dealAmount' => $this->decimal($this->value($row, 'DEAL_AMOUNT', 'dealAmount')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'firstContactTime' => $this->value($row, 'FIRST_CONTACT_TIME', 'firstContactTime'),
            ];
        }, $rows);
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

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
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
