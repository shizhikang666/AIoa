<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only warehouse queries compatible with Java WarehousesController.
 */
class WarehousesService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'name' => 'NAME',
        'code' => 'CODE',
        'address' => 'ADDRESS',
        'sortCode' => 'SORT_CODE',
        'user' => 'USER',
        'org' => 'ORG',
        'orgId' => 'ORG',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
        'tenantId' => 'TENANT_ID',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->warehouseQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->warehouseQuery($filters, $payload, true), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->warehouseRows($rows),
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
        $rows = $this->applySort($this->warehouseQuery($filters, $payload, false), $filters)
            ->select()
            ->toArray();

        return $this->warehouseRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->warehouseQuery(['id' => $id], $payload, false)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('warehouse not found', 404);
        }

        return $this->warehouseRows([$row])[0];
    }

    public function add(array $input, array $payload = []): array
    {
        $this->requiredInput($input, 'name');
        $this->requiredInput($input, 'code');

        return Db::transaction(function () use ($input, $payload): array {
            $userId = $this->currentUserId($payload);
            $id = $this->newId();
            $row = [
                'ID' => $id,
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $this->defaultOrgId($payload),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $this->tenantId($input, $payload),
            ];

            $this->applyWarehouseInput($row, $input, false);
            $this->assertNewWarehouseWritable($row, $payload);

            Db::name('warehouses')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $this->assertWarehouseWritable($id, $payload, 'edit');
            $userId = $this->currentUserId($payload);
            $row = [
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $userId !== '' ? $userId : null,
            ];

            $this->applyWarehouseInput($row, $input, true);
            if (array_key_exists('ORG', $row)) {
                $this->assertOrgWritable((string)$row['ORG'], $payload);
            }

            $updated = Db::name('warehouses')
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
                $this->assertWarehouseWritable($id, $payload, 'delete');
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('warehouses')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    private function applyWarehouseInput(array &$row, array $input, bool $allowOrg): void
    {
        $fields = [
            'name' => 'NAME',
            'code' => 'CODE',
            'address' => 'ADDRESS',
            'sortCode' => 'SORT_CODE',
            'extJson' => 'EXT_JSON',
        ];

        foreach ($fields as $inputKey => $column) {
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }

            $row[$column] = $inputKey === 'sortCode'
                ? $this->nullableInt($input[$inputKey])
                : (in_array($inputKey, ['name', 'code'], true)
                    ? $this->requiredInput($input, $inputKey)
                    : $this->nullableString($input[$inputKey]));
        }

        if ($allowOrg && array_key_exists('org', $input)) {
            $row['ORG'] = $this->requiredInput($input, 'org');
        }
    }

    private function assertWarehouseWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeWarehouse($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $warehouseOrg = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($warehouseOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($row['USER'] ?? ''));
        if ($currentUserId !== '' && $ownerUserId === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this warehouse", 403);
    }

    private function assertNewWarehouseWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $warehouseOrg = trim((string)($row['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && in_array($warehouseOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($row['USER'] ?? ''));
        if ($currentUserId !== '' && $ownerUserId === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this warehouse', 403);
    }

    private function assertOrgWritable(string $orgId, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        $payloadOrg = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($payloadOrg !== '' && $payloadOrg === $orgId) {
            return;
        }

        throw new RuntimeException('no permission to assign this warehouse organization', 403);
    }

    private function warehouseQuery(array $filters, array $payload, bool $applyDataScope)
    {
        $query = Db::name('warehouses')
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
            'code' => 'CODE',
            'address' => 'ADDRESS',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('NAME', $keyword)
                    ->whereOr('CODE', 'like', $keyword)
                    ->whereOr('ADDRESS', 'like', $keyword);
            });
        }

        if (!empty($filters['user'])) {
            $query->where('USER', (string)$filters['user']);
        }

        if (!empty($filters['userId'])) {
            $query->where('USER', (string)$filters['userId']);
        }

        if (!empty($filters['orgId'])) {
            $query->whereIn('ORG', $this->orgAndChildren((string)$filters['orgId']));
        }

        if (!empty($filters['org'])) {
            $query->where('ORG', (string)$filters['org']);
        }

        $scopeOrgIds = $applyDataScope ? $this->scopeOrgIds($payload) : [];
        if ($scopeOrgIds !== []) {
            $ownerUserIds = $this->ownerUserIdsByOrg($scopeOrgIds);
            $query->where(function ($query) use ($scopeOrgIds, $ownerUserIds): void {
                $query->whereIn('ORG', $scopeOrgIds);
                if ($ownerUserIds !== []) {
                    $query->whereOr('USER', 'in', $ownerUserIds);
                }
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('ID', 'asc');
        }

        return $query->order('SORT_CODE', 'asc')->order('ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function warehouseRows(array $rows): array
    {
        $ownerNames = $this->ownerNames($rows);
        $orgNames = $this->orgNames($rows);

        return array_map(fn (array $row): array => $this->warehouseRow($row, $ownerNames, $orgNames), $rows);
    }

    /**
     * @param array<string, string> $ownerNames
     * @param array<string, string> $orgNames
     */
    private function warehouseRow(array $row, array $ownerNames = [], array $orgNames = []): array
    {
        $userId = $this->value($row, 'USER', 'user');
        $orgId = $this->value($row, 'ORG', 'org');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'name' => $this->value($row, 'NAME', 'name'),
            'code' => $this->value($row, 'CODE', 'code'),
            'address' => $this->value($row, 'ADDRESS', 'address'),
            'sortCode' => $this->integer($this->value($row, 'SORT_CODE', 'sortCode')),
            'user' => $userId,
            'headName' => $userId !== null ? ($ownerNames[(string)$userId] ?? $this->value($row, 'headName')) : $this->value($row, 'headName'),
            'org' => $orgId,
            'orgName' => $orgId !== null ? ($orgNames[(string)$orgId] ?? $this->value($row, 'orgName')) : $this->value($row, 'orgName'),
            'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function ownerNames(array $rows): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['USER'] ?? $row['user'] ?? ''),
            $rows
        ))));
        if ($userIds === []) {
            return [];
        }

        return Db::name('sys_user')->whereIn('ID', $userIds)->column('NAME', 'ID');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function orgNames(array $rows): array
    {
        $orgIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string)($row['ORG'] ?? $row['org'] ?? ''),
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
    private function ownerUserIdsByOrg(array $orgIds): array
    {
        if ($orgIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            Db::name('sys_user')->whereIn('ORG_ID', $orgIds)->column('ID')
        ))));
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
            $parentId = (string)($row['PARENT_ID'] ?? '');
            $childrenByParent[$parentId][] = (string)$row['ID'];
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

    private function activeWarehouse(string $id, array $payload): array
    {
        $query = Db::name('warehouses')->where('ID', $id);
        $this->whereNotDeleted($query, 'DELETE_FLAG');

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('warehouse not found', 404);
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

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
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
