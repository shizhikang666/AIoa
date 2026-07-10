<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysPosition;
use app\support\TenantScope;
use RuntimeException;
use think\facade\Db;

/**
 * Position queries and base writes compatible with Java SysPositionService.
 */
class PositionService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const CATEGORY_HIGH = 'HIGH';
    private const CATEGORY_MIDDLE = 'MIDDLE';
    private const CATEGORY_LOW = 'LOW';

    public function page(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $records = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->positionRow($row), $records),
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
    public function all(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        $rows = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->positionRow($row), $rows);
    }

    public function detail(string $id, mixed $payload = []): ?array
    {
        $row = $this->baseQuery(TenantScope::scopedFilters(['id' => $id], $payload))->find();

        return $row ? $this->positionRow($row->toArray()) : null;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function add(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->positionWriteData($input);
        $org = $this->activeOrgRow($data['ORG_ID']);
        $this->ensurePositionWriteAllowed($payload, $data, $bizScope, 'add', null);
        $this->assertDuplicatePositionName($data['ORG_ID'], $data['NAME'], null);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $data = array_merge($data, [
            'ID' => $this->newId(),
            'CODE' => $this->newPositionCode(),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            'TENANT_ID' => $this->tenantIdForWrite($payload, $org, null),
        ]);

        Db::name('sys_position')->insert($data);

        return $this->positionRow($data);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function edit(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $id = trim((string)($input['id'] ?? $input['ID'] ?? ''));
        if ($id === '') {
            throw new RuntimeException('missing id', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $existing = $this->activePositionRow($id);
        $data = $this->positionWriteData($input, $existing);
        $org = $this->activeOrgRow($data['ORG_ID']);
        $this->ensurePositionWriteAllowed($payload, $data, $bizScope, 'edit', $existing);
        $this->assertDuplicatePositionName($data['ORG_ID'], $data['NAME'], $id);
        $this->tenantIdForWrite($payload, $org, $existing);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_position')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        return $this->positionRow($this->activePositionRow($id));
    }

    /**
     * @param array<int, mixed> $ids
     * @param array<string, mixed>|mixed $payload
     */
    public function delete(array $ids, mixed $payload = [], bool $bizScope = false): array
    {
        $idList = $this->idInputList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $payload = is_array($payload) ? $payload : [];
        $rows = $this->activePositionRows($idList);
        $this->ensurePositionDeleteAllowed($payload, $rows, $bizScope);
        foreach ($rows as $row) {
            $this->ensureTenantCompatible($payload, $row);
        }
        $this->assertPositionDeleteDependencies($idList);

        $operatorId = $this->payloadUserId($payload);
        $updated = Db::transaction(function () use ($idList, $operatorId): int {
            return Db::name('sys_position')
                ->whereIn('ID', $idList)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                ]);
        });

        return [
            'ids' => $idList,
            'count' => $updated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function selector(array $filters = [], mixed $payload = []): array
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $rows = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        $records = array_map(function (array $row): array {
            $position = $this->positionRow($row);

            return array_merge($position, [
                'value' => $position['id'] ?? null,
                'label' => $position['name'] ?? null,
                'title' => $position['name'] ?? null,
            ]);
        }, $rows);

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

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function positionWriteData(array $input, ?array $existing = null): array
    {
        $orgId = $this->requiredInput($input, ['orgId', 'ORG_ID'], 'orgId');
        $name = $this->requiredInput($input, ['name', 'NAME'], 'name');
        $category = strtoupper($this->requiredInput($input, ['category', 'CATEGORY'], 'category'));
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');

        if (!in_array($category, [self::CATEGORY_HIGH, self::CATEGORY_MIDDLE, self::CATEGORY_LOW], true)) {
            throw new RuntimeException('invalid category', 400);
        }
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'ORG_ID' => $orgId,
            'NAME' => $name,
            'CATEGORY' => $category,
            'SORT_CODE' => (int)$sortCode,
            'EXT_JSON' => $extJson,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredInput(array $input, array $keys, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$label}", 400);
    }

    private function normalizeJsonInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function activePositionRow(string $id): array
    {
        $row = Db::name('sys_position')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!$row) {
            throw new RuntimeException('position not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activePositionRows(array $ids): array
    {
        $rows = Db::name('sys_position')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $found = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['ID'] ?? ''), $rows)));
        $missing = array_values(array_diff($ids, $found));
        if ($missing !== []) {
            throw new RuntimeException('position not found', 404);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function activeOrgRow(string $id): array
    {
        $row = Db::name('sys_org')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!$row) {
            throw new RuntimeException('org not found', 404);
        }

        return $row;
    }

    private function assertDuplicatePositionName(string $orgId, string $name, ?string $ignoreId): void
    {
        $query = Db::name('sys_position')
            ->where('ORG_ID', $orgId)
            ->where('NAME', $name)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('same-org position name exists', 400);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $org
     * @param array<string, mixed>|null $existing
     */
    private function tenantIdForWrite(array $payload, array $org, ?array $existing): string
    {
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $orgTenantId = trim((string)($org['TENANT_ID'] ?? ''));
        $existingTenantId = trim((string)($existing['TENANT_ID'] ?? ''));

        if ($existingTenantId !== '') {
            if ($orgTenantId !== '' && $orgTenantId !== '0' && $existingTenantId !== '0' && $orgTenantId !== $existingTenantId) {
                throw new RuntimeException('tenant mismatch', 403);
            }
            if ($payloadTenantId !== '' && $payloadTenantId !== $existingTenantId && !TenantScope::canCrossTenant($payload)) {
                throw new RuntimeException('permission denied', 403);
            }

            return $existingTenantId;
        }

        if ($orgTenantId !== '') {
            if ($payloadTenantId !== '' && $payloadTenantId !== $orgTenantId && !TenantScope::canCrossTenant($payload)) {
                throw new RuntimeException('permission denied', 403);
            }

            return $orgTenantId;
        }

        return $payloadTenantId !== '' ? $payloadTenantId : '0';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     */
    private function ensurePositionWriteAllowed(array $payload, array $data, bool $bizScope, string $action, ?array $existing): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasPositionPermission($payload, $bizScope, $action)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && in_array((string)$data['ORG_ID'], $scopeOrgIds, true)) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $rows
     */
    private function ensurePositionDeleteAllowed(array $payload, array $rows, bool $bizScope): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasPositionPermission($payload, $bizScope, 'delete')) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds === []) {
            throw new RuntimeException('permission denied', 403);
        }

        foreach ($rows as $row) {
            if (!in_array((string)($row['ORG_ID'] ?? ''), $scopeOrgIds, true)) {
                throw new RuntimeException('permission denied', 403);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasPositionPermission(array $payload, bool $bizScope, string $action): bool
    {
        $action = strtolower($action);
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ["/biz/position/{$action}", "bizposition{$action}"]
            : ["/sys/position/{$action}", "sysposition{$action}"];

        foreach ($codes as $code) {
            $normalized = strtolower(str_replace(['/', ':', '_', '-'], '', $code));
            $lower = strtolower($code);
            foreach ($needles as $needle) {
                if ($lower === $needle || $normalized === str_replace(['/', ':', '_', '-'], '', $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $row
     */
    private function ensureTenantCompatible(array $payload, array $row): void
    {
        TenantScope::assertCompatible($payload, $row['TENANT_ID'] ?? null);
    }

    /**
     * @param array<int, string> $positionIds
     */
    private function assertPositionDeleteDependencies(array $positionIds): void
    {
        $directUserCount = Db::name('sys_user')
            ->whereIn('POSITION_ID', $positionIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
        if ($directUserCount > 0) {
            throw new RuntimeException('position has users', 400);
        }

        if ($this->positionJsonReferencesPosition($positionIds)) {
            throw new RuntimeException('position has users', 400);
        }
    }

    /**
     * @param array<int, string> $positionIds
     */
    private function positionJsonReferencesPosition(array $positionIds): bool
    {
        $idMap = array_fill_keys($positionIds, true);
        $rows = Db::name('sys_user')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->whereNotNull('POSITION_JSON')
            ->where('POSITION_JSON', '<>', '')
            ->field(['POSITION_JSON'])
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $json = (string)($row['POSITION_JSON'] ?? '');
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && $this->valueContainsPositionId($decoded, $idMap)) {
                return true;
            }

            foreach ($positionIds as $positionId) {
                if ($positionId !== '' && str_contains($json, $positionId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, bool> $idMap
     */
    private function valueContainsPositionId(mixed $value, array $idMap): bool
    {
        if (is_scalar($value) && isset($idMap[(string)$value])) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (in_array((string)$key, ['positionId', 'position_id', 'POSITION_ID'], true) && isset($idMap[(string)$item])) {
                return true;
            }
            if ($this->valueContainsPositionId($item, $idMap)) {
                return true;
            }
        }

        return false;
    }

    private function idInputList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $id = trim((string)($item['id'] ?? $item['positionId'] ?? $item['value'] ?? $item['key'] ?? ''));
            } else {
                $id = trim((string)$item);
            }

            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAdminCompatible(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        foreach ($this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []) as $roleCode) {
            if (in_array(strtolower($roleCode), ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
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

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function newPositionCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Db::name('sys_position')
                ->where('CODE', $code)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->count();
            if ($exists === 0) {
                return $code;
            }
        }

        return substr($this->newId(), -10);
    }

    private function baseQuery(array $filters)
    {
        $query = SysPosition::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['orgId'])) {
            $query->where('ORG_ID', (string)$filters['orgId']);
        }

        if (!empty($filters['name'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['name']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('NAME', '%' . trim((string)$filters['searchKey']) . '%');
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID', (string)$filters['tenantId']);
        }

        return $query;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? $filters['size'] ?? 20)));

        return [$page, $limit];
    }

    private function positionRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }
}
