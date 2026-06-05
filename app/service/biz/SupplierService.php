<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only supplier queries compatible with Java SupplierController.
 */
class SupplierService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const ENABLE = 'ENABLE';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'contacts' => 'CONTACTS',
        'phone' => 'PHONE',
        'bankName' => 'BANK_NAME',
        'bankAccount' => 'BANK_ACCOUNT',
        'status' => 'STATUS',
        'enterpriseNature' => 'ENTERPRISE_NATURE',
        'taxRegistrationNumber' => 'TAX_REGISTRATION_NUMBER',
        'paymentMethod' => 'PAYMENT_METHOD',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
        'tenantId' => 'TENANT_ID',
        'aliasName' => 'ALIAS_NAME',
        'org' => 'org',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->supplierQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->supplierQuery($filters, $payload, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->supplierRows($rows),
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
        $rows = $this->applySort($this->supplierQuery($filters, $payload, false), $filters)
            ->select()
            ->toArray();

        return $this->supplierRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function queryByName(string $name, array $payload = []): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('name is required', 400);
        }

        $keyword = '%' . $name . '%';
        $rows = $this->supplierQuery(['status' => self::ENABLE], $payload, false)
            ->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)->whereOr('ALIAS_NAME', 'like', $keyword);
            })
            ->order('SORT_CODE', 'asc')
            ->order('ID', 'asc')
            ->select()
            ->toArray();

        return $this->supplierRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->supplierQuery(['id' => $id], $payload, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('supplier not found', 404);
        }

        return $this->supplierRows([$row])[0];
    }

    public function add(array $input, array $payload = []): array
    {
        $this->requiredInput($input, 'name');
        $this->requiredInput($input, 'contacts');
        $this->requiredInput($input, 'phone');

        return Db::transaction(function () use ($input, $payload): array {
            $userId = $this->currentUserId($payload);
            $id = $this->newId();
            $row = [
                'ID' => $id,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload),
                'org' => $this->defaultOrgId($payload),
            ];

            $this->applySupplierInput($row, $input);
            if (empty($row['STATUS'])) {
                $row['STATUS'] = self::ENABLE;
            }
            $this->assertNewSupplierWritable($row, $payload);

            Db::name('supplier')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $this->requiredInput($input, 'name');
        $this->requiredInput($input, 'contacts');
        $this->requiredInput($input, 'phone');
        $this->requiredInput($input, 'status');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->assertSupplierWritable($id, $payload, 'edit');
            $userId = $this->currentUserId($payload);
            $row = [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $userId !== '' ? $userId : null,
            ];

            $this->applySupplierInput($row, $input);

            $updated = Db::name('supplier')
                ->where('ID', $id)
                ->update($row);

            return ['id' => $id, 'count' => $updated];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            foreach ($idList as $id) {
                $this->assertSupplierWritable($id, $payload, 'delete');
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('supplier')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function applySupplierInput(array &$row, array $input): void
    {
        $fields = [
            'name' => 'NAME',
            'contacts' => 'CONTACTS',
            'phone' => 'PHONE',
            'bankName' => 'BANK_NAME',
            'bankAccount' => 'BANK_ACCOUNT',
            'status' => 'STATUS',
            'enterpriseNature' => 'ENTERPRISE_NATURE',
            'taxRegistrationNumber' => 'TAX_REGISTRATION_NUMBER',
            'paymentMethod' => 'PAYMENT_METHOD',
            'sortCode' => 'SORT_CODE',
            'aliasName' => 'ALIAS_NAME',
            'extJson' => 'EXT_JSON',
        ];

        foreach ($fields as $inputKey => $column) {
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }

            $row[$column] = $inputKey === 'sortCode'
                ? $this->nullableInt($input[$inputKey])
                : $this->nullableString($input[$inputKey]);
        }
    }

    private function assertSupplierWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeSupplier($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $supplierOrg = trim((string)($row['org'] ?? $row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($supplierOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this supplier", 403);
    }

    private function assertNewSupplierWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $supplierOrg = trim((string)($row['org'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($supplierOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this supplier', 403);
    }

    private function supplierQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('supplier')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        foreach ([
            'name' => 'NAME',
            'contacts' => 'CONTACTS',
            'phone' => 'PHONE',
            'bankName' => 'BANK_NAME',
            'bankAccount' => 'BANK_ACCOUNT',
            'enterpriseNature' => 'ENTERPRISE_NATURE',
            'taxRegistrationNumber' => 'TAX_REGISTRATION_NUMBER',
            'paymentMethod' => 'PAYMENT_METHOD',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)
                    ->whereOr('ALIAS_NAME', 'like', $keyword)
                    ->whereOr('CONTACTS', 'like', $keyword);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('STATUS', (string)$filters['status']);
        }

        if (!empty($filters['orgId'])) {
            $query->where('org', (string)$filters['orgId']);
        }

        $scopeOrgIds = $applyDataScope ? $this->scopeOrgIds($payload) : [];
        if ($scopeOrgIds !== []) {
            $query->whereIn('org', $scopeOrgIds);
        }

        return $query;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function supplierRows(array $rows): array
    {
        $orgNames = $this->orgNames($rows);

        return array_map(fn (array $row): array => $this->supplierRow($row, $orgNames), $rows);
    }

    /**
     * @param array<string, string> $orgNames
     */
    private function supplierRow(array $row, array $orgNames = []): array
    {
        $orgId = $row['org'] ?? $row['ORG'] ?? null;

        return [
            'id' => $row['ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'contacts' => $row['CONTACTS'] ?? null,
            'phone' => $row['PHONE'] ?? null,
            'bankName' => $row['BANK_NAME'] ?? null,
            'bankAccount' => $row['BANK_ACCOUNT'] ?? null,
            'status' => $row['STATUS'] ?? null,
            'enterpriseNature' => $row['ENTERPRISE_NATURE'] ?? null,
            'taxRegistrationNumber' => $row['TAX_REGISTRATION_NUMBER'] ?? null,
            'paymentMethod' => $row['PAYMENT_METHOD'] ?? null,
            'sortCode' => isset($row['SORT_CODE']) ? (int)$row['SORT_CODE'] : null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
            'aliasName' => $row['ALIAS_NAME'] ?? null,
            'org' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? null) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['org'] ?? $row['ORG'] ?? ''),
            $rows
        ))));
        if ($orgIds === []) {
            return [];
        }

        return Db::name('sys_org')->whereIn('ID', $orgIds)->column('NAME', 'ID');
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

    private function activeSupplier(string $id, array $payload): array
    {
        $query = Db::name('supplier')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('supplier not found', 404);
        }

        return $row;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
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

        $value = is_array($value) ? implode(',', array_map('strval', $value)) : (string)$value;

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string)$value);

        return $value !== '' ? (int)$value : null;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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

        return array_values(array_unique(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string)($item['id'] ?? $item['ID'] ?? ''));
            }

            return trim((string)$item);
        }, $value))));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
