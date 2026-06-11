<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Settlement-account queries and base writes compatible with Java SettlementAccountController.
 */
class SettlementAccountService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
    private const DISABLED = 'DISABLED';
    private const ACCOUNT_FIELDS = <<<SQL
a.ID AS ID,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
a.INITIAL_AMOUNT AS INITIAL_AMOUNT,
a.CURRENT_AMOUNT AS CURRENT_AMOUNT,
a.ACCOUNT_STATUS AS ACCOUNT_STATUS,
a.SORT_CODE AS SORT_CODE,
a.DELETE_FLAG AS DELETE_FLAG,
a.CREATE_TIME AS CREATE_TIME,
a.CREATE_USER AS CREATE_USER,
a.UPDATE_TIME AS UPDATE_TIME,
a.UPDATE_USER AS UPDATE_USER,
a.EXT_JSON AS EXT_JSON,
a.TENANT_ID AS TENANT_ID,
a.VERSION AS VERSION,
a.org AS ORG,
a.ARCHIVE_AMOUNT AS ARCHIVE_AMOUNT,
a.ARCHIVE_TIME AS ARCHIVE_TIME,
org.NAME AS ORG_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'a.ID',
        'accountName' => 'a.ACCOUNT_NAME',
        'accountNumber' => 'a.ACCOUNT_NUMBER',
        'initialAmount' => 'a.INITIAL_AMOUNT',
        'currentAmount' => 'a.CURRENT_AMOUNT',
        'accountStatus' => 'a.ACCOUNT_STATUS',
        'sortCode' => 'a.SORT_CODE',
        'createTime' => 'a.CREATE_TIME',
        'updateTime' => 'a.UPDATE_TIME',
        'tenantId' => 'a.TENANT_ID',
        'version' => 'a.VERSION',
        'org' => 'a.org',
        'orgName' => 'org.NAME',
        'archiveAmount' => 'a.ARCHIVE_AMOUNT',
        'archiveTime' => 'a.ARCHIVE_TIME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->accountQuery($filters, $payload, false)->count();
        $rows = $this->applySort($this->accountQuery($filters, $payload, false), $filters)
            ->field(self::ACCOUNT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->accountRows($rows),
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
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->accountQuery($filters, $payload, true), $filters)
            ->field(self::ACCOUNT_FIELDS)
            ->select()
            ->toArray();

        return $this->accountRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->accountQuery(['id' => $id], $payload, false)
            ->field(self::ACCOUNT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return $this->accountRows([$row])[0];
    }

    public function queryName(string $id, array $payload = []): string
    {
        $row = $this->accountQuery(['id' => $id], $payload, false)
            ->field(['a.ACCOUNT_NAME'])
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return (string)($row['ACCOUNT_NAME'] ?? '');
    }

    public function add(array $input, array $payload = []): array
    {
        $accountName = $this->requiredInput($input, 'accountName');
        $accountNumber = $this->requiredInput($input, 'accountNumber');
        $status = $this->accountStatus($input['accountStatus'] ?? self::ENABLE);

        return Db::transaction(function () use ($input, $payload, $accountName, $accountNumber, $status): array {
            $tenantId = $this->tenantId($input, $payload);
            $this->assertUniqueAccountName($accountName, $tenantId);

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $id = $this->newId();
            $initialAmount = $this->decimalAmount($input['initialAmount'] ?? 0);
            $row = [
                'ID' => $id,
                'ACCOUNT_NAME' => $accountName,
                'ACCOUNT_NUMBER' => $accountNumber,
                'INITIAL_AMOUNT' => $initialAmount,
                'CURRENT_AMOUNT' => $initialAmount,
                'ACCOUNT_STATUS' => $status,
                'SORT_CODE' => $this->nullableInt($input['sortCode'] ?? null),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ];

            $orgId = $this->nullableString($input['org'] ?? $input['orgId'] ?? null) ?? $this->defaultOrgId($payload);
            if ($orgId !== null) {
                $row['org'] = $orgId;
            }
            $this->assertNewAccountWritable($row, $payload);

            Db::name('settlement_account')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $accountName = $this->requiredInput($input, 'accountName');

        return Db::transaction(function () use ($id, $input, $payload, $accountName): array {
            $account = $this->assertAccountWritable($id, $payload, 'edit');
            $tenantId = (string)($account['TENANT_ID'] ?? $this->tenantId($input, $payload));
            if ($accountName !== (string)($account['ACCOUNT_NAME'] ?? '')) {
                $this->assertUniqueAccountName($accountName, $tenantId, $id);
            }

            $row = [
                'ACCOUNT_NAME' => $accountName,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '') ? $this->currentUserId($payload) : null,
            ];
            if (array_key_exists('accountStatus', $input)) {
                $row['ACCOUNT_STATUS'] = $this->accountStatus($input['accountStatus']);
            }
            if (array_key_exists('sortCode', $input)) {
                $row['SORT_CODE'] = $this->nullableInt($input['sortCode']);
            }
            if (array_key_exists('org', $input) || array_key_exists('orgId', $input)) {
                $orgId = $this->nullableString($input['org'] ?? $input['orgId'] ?? null);
                $this->assertOrgWritable($orgId, $payload);
                $row['org'] = $orgId;
            }

            $updated = Db::name('settlement_account')
                ->where('ID', $id)
                ->update($row);

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function editStatus(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $status = $this->accountStatus($input['accountStatus'] ?? null);

        return Db::transaction(function () use ($id, $status, $payload): array {
            $this->assertAccountWritable($id, $payload, 'edit status');
            $userId = $this->currentUserId($payload);
            $updated = Db::name('settlement_account')
                ->where('ID', $id)
                ->update([
                    'ACCOUNT_STATUS' => $status,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['id' => $id, 'count' => $updated];
        });
    }

    private function assertAccountWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeAccount($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $accountOrg = trim((string)($row['org'] ?? $row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $accountOrg !== '' && in_array($accountOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this settlement account", 403);
    }

    private function assertNewAccountWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $accountOrg = trim((string)($row['org'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $accountOrg !== '' && in_array($accountOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this settlement account', 403);
    }

    private function assertOrgWritable(?string $orgId, array $payload): void
    {
        if ($orgId === null || $orgId === '' || $this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        throw new RuntimeException('no permission to use this organization', 403);
    }

    private function activeAccount(string $id, array $payload): array
    {
        $query = Db::name('settlement_account')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return $row;
    }

    private function assertUniqueAccountName(string $accountName, string $tenantId, ?string $ignoreId = null): void
    {
        $query = Db::name('settlement_account')
            ->where('ACCOUNT_NAME', $accountName)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('settlement account name already exists', 400);
        }
    }

    private function accountStatus(mixed $value): string
    {
        $status = trim((string)$value);
        if (!in_array($status, [self::ENABLE, self::DISABLED], true)) {
            throw new RuntimeException('unsupported settlement account status', 400);
        }

        return $status;
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    private function tenantId(array $input, array $payload): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function defaultOrgId(array $payload): ?string
    {
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            return $orgId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '') {
            return null;
        }

        $user = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ORG_ID')
            ->find();
        if (!is_array($user) || $user === []) {
            return null;
        }

        $orgId = trim((string)($user['ORG_ID'] ?? ''));

        return $orgId !== '' ? $orgId : null;
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
            $ids = array_merge($ids, array_map(static function (mixed $scope): string {
                if (!is_array($scope)) {
                    return '';
                }

                return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
            }, $scopes));
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $ids
        ))));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimalAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid amount', 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function accountQuery(array $filters, array $payload, bool $enabledOnly)
    {
        $query = Db::name('settlement_account')
            ->alias('a')
            ->leftJoin('sys_org org', 'org.ID = a.org')
            ->where(function ($query): void {
                $query->whereNull('a.DELETE_FLAG')->whereOr('a.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('a.TENANT_ID', $tenantId);
        }

        if ($enabledOnly) {
            $query->where('a.ACCOUNT_STATUS', self::ENABLE);
        } elseif (!empty($filters['accountStatus'])) {
            $query->where('a.ACCOUNT_STATUS', (string)$filters['accountStatus']);
        }

        if (!empty($filters['id'])) {
            $query->where('a.ID', (string)$filters['id']);
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        if (!empty($filters['name'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['accountNumber'])) {
            $query->whereLike('a.ACCOUNT_NUMBER', '%' . trim((string)$filters['accountNumber']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $query->where('a.org', (string)$filters['orgId']);
        } elseif (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('a.org', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('a.ACCOUNT_NAME', $keyword)
                    ->whereOr('a.ACCOUNT_NUMBER', 'like', $keyword)
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('a.ID', 'asc');
        }

        return $query->order('a.SORT_CODE', 'asc')->order('a.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function accountRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->accountRow($row), $rows);
    }

    private function accountRow(array $row): array
    {
        $extJson = (string)($this->value($row, 'EXT_JSON', 'extJson') ?? '');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'initialAmount' => $this->decimal($this->value($row, 'INITIAL_AMOUNT', 'initialAmount')),
            'currentAmount' => $this->decimal($this->value($row, 'CURRENT_AMOUNT', 'currentAmount')),
            'accountStatus' => $this->value($row, 'ACCOUNT_STATUS', 'accountStatus'),
            'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'extJson' => $extJson,
            'ext' => $this->decodeJsonObject($extJson),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => $this->integer($this->value($row, 'VERSION', 'version')),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'archiveAmount' => $this->decimal($this->value($row, 'ARCHIVE_AMOUNT', 'archiveAmount')),
            'archiveTime' => $this->value($row, 'ARCHIVE_TIME', 'archiveTime'),
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function decodeJsonObject(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
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
