<?php

declare(strict_types=1);

namespace app\service\user;

use app\model\SysOrg;
use RuntimeException;
use think\facade\Db;

/**
 * Organization queries and base writes compatible with Java SysOrgService.
 */
class OrgService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const CATEGORY_COMPANY = 'COMPANY';
    private const CATEGORY_DEPT = 'DEPT';

    public function __construct(private readonly TreeBuilder $treeBuilder = new TreeBuilder())
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return array_map(
            fn (array $row): array => $this->orgRow($row),
            $this->rawRows($filters)
        );
    }

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->baseQuery($filters)->count();
        $records = $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->orgRow($row), $records),
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
    public function tree(array $filters = []): array
    {
        return $this->normalizeTree($this->treeBuilder->build($this->rawRows($filters)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function selector(array $filters = []): array
    {
        return $this->treeBuilder->toSelector($this->treeBuilder->build($this->rawRows($filters)));
    }

    public function detail(string $id): ?array
    {
        $row = $this->baseQuery(['id' => $id])->find();

        return $row ? $this->orgRow($row->toArray()) : null;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|mixed $payload
     */
    public function add(array $input, mixed $payload = [], bool $bizScope = false): array
    {
        $payload = is_array($payload) ? $payload : [];
        $data = $this->orgWriteData($input);
        $parent = $this->activeParentOrg($data['PARENT_ID']);
        $this->ensureOrgWriteAllowed($payload, $data, $bizScope, 'add', null);
        $this->assertDuplicateOrgName($data['PARENT_ID'], $data['NAME'], null);

        $tenantId = $this->tenantIdForWrite($payload, $parent, null);
        $this->assertDirector($data['DIRECTOR_ID'], $tenantId);

        $now = date('Y-m-d H:i:s');
        $operatorId = $this->payloadUserId($payload);
        $data = array_merge($data, [
            'ID' => $this->newId(),
            'CODE' => $this->newOrgCode(),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $operatorId !== '' ? $operatorId : null,
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
            'TENANT_ID' => $tenantId,
        ]);

        Db::name('sys_org')->insert($data);

        return $this->orgRow($data);
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
        $existing = $this->activeOrgRow($id);
        $data = $this->orgWriteData($input, $existing);
        $parent = $this->activeParentOrg($data['PARENT_ID']);
        $this->ensureParentNotSelfOrChild($id, $data['PARENT_ID']);
        $this->ensureOrgWriteAllowed($payload, $data, $bizScope, 'edit', $existing);
        $this->assertDuplicateOrgName($data['PARENT_ID'], $data['NAME'], $id);

        $tenantId = $this->tenantIdForWrite($payload, $parent, $existing);
        $this->assertDirector($data['DIRECTOR_ID'], $tenantId);

        $operatorId = $this->payloadUserId($payload);
        $update = array_merge($data, [
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
        ]);

        Db::name('sys_org')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($update);

        $row = $this->activeOrgRow($id);

        return $this->orgRow($row);
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
        $selectedRows = $this->activeOrgRows($idList);
        $this->ensureOrgDeleteAllowed($payload, $selectedRows, $bizScope);

        $expandedIds = $this->orgAndChildren($idList);
        if ($expandedIds === []) {
            throw new RuntimeException('org not found', 404);
        }
        $expandedRows = $this->activeOrgRows($expandedIds);
        foreach ($expandedRows as $row) {
            $this->ensureTenantCompatible($payload, $row);
        }
        $this->assertOrgDeleteDependencies($expandedIds);

        $operatorId = $this->payloadUserId($payload);
        $updated = Db::transaction(function () use ($expandedIds, $operatorId): int {
            return Db::name('sys_org')
                ->whereIn('ID', $expandedIds)
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
            'expandedIds' => $expandedIds,
            'count' => $updated,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rawRows(array $filters = []): array
    {
        return $this->baseQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();
    }

    private function baseQuery(array $filters)
    {
        $query = SysOrg::where('DELETE_FLAG', self::NOT_DELETE);

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['parentId'])) {
            $query->where('PARENT_ID', (string)$filters['parentId']);
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

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function orgWriteData(array $input, ?array $existing = null): array
    {
        $parentId = $this->requiredInput($input, ['parentId', 'PARENT_ID'], 'parentId');
        $name = $this->requiredInput($input, ['name', 'NAME'], 'name');
        $category = strtoupper($this->requiredInput($input, ['category', 'CATEGORY'], 'category'));
        $sortCode = $this->requiredInput($input, ['sortCode', 'SORT_CODE'], 'sortCode');

        if (!in_array($category, [self::CATEGORY_COMPANY, self::CATEGORY_DEPT], true)) {
            throw new RuntimeException('invalid category', 400);
        }
        if (!is_numeric($sortCode)) {
            throw new RuntimeException('invalid sortCode', 400);
        }

        $directorId = $this->optionalInput($input, ['directorId', 'DIRECTOR_ID']);
        $extJson = array_key_exists('extJson', $input) || array_key_exists('EXT_JSON', $input)
            ? $this->normalizeJsonInput($input['extJson'] ?? $input['EXT_JSON'] ?? null)
            : ($existing['EXT_JSON'] ?? null);

        return [
            'PARENT_ID' => $parentId,
            'DIRECTOR_ID' => $directorId !== '' ? $directorId : null,
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

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function optionalInput(array $input, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return trim((string)$input[$key]);
            }
        }

        return '';
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
     * @return array<string, mixed>|null
     */
    private function activeParentOrg(string $parentId): ?array
    {
        if ($parentId === '0') {
            return null;
        }

        return $this->activeOrgRow($parentId);
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

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeOrgRows(array $ids): array
    {
        $rows = Db::name('sys_org')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $found = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['ID'] ?? ''), $rows)));
        $missing = array_values(array_diff($ids, $found));
        if ($missing !== []) {
            throw new RuntimeException('org not found', 404);
        }

        return $rows;
    }

    private function assertDuplicateOrgName(string $parentId, string $name, ?string $ignoreId): void
    {
        $query = Db::name('sys_org')
            ->where('PARENT_ID', $parentId)
            ->where('NAME', $name)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('same-level org name exists', 400);
        }
    }

    private function ensureParentNotSelfOrChild(string $id, string $parentId): void
    {
        if ($parentId === '0') {
            return;
        }

        if (in_array($parentId, $this->orgAndChildren([$id]), true)) {
            throw new RuntimeException('parent cannot be self or child', 400);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $parent
     * @param array<string, mixed>|null $existing
     */
    private function tenantIdForWrite(array $payload, ?array $parent, ?array $existing): string
    {
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $parentTenantId = trim((string)($parent['TENANT_ID'] ?? ''));
        $existingTenantId = trim((string)($existing['TENANT_ID'] ?? ''));

        if ($existingTenantId !== '') {
            if ($parentTenantId !== '' && $parentTenantId !== '0' && $existingTenantId !== '0' && $parentTenantId !== $existingTenantId) {
                throw new RuntimeException('tenant mismatch', 403);
            }
            if ($payloadTenantId !== '' && $payloadTenantId !== $existingTenantId && !$this->isAdminCompatible($payload)) {
                throw new RuntimeException('permission denied', 403);
            }

            return $existingTenantId;
        }

        if ($parentTenantId !== '') {
            if ($payloadTenantId !== '' && $payloadTenantId !== $parentTenantId && !$this->isAdminCompatible($payload)) {
                throw new RuntimeException('permission denied', 403);
            }

            return $parentTenantId;
        }

        return $payloadTenantId !== '' ? $payloadTenantId : '0';
    }

    private function assertDirector(?string $directorId, string $tenantId): void
    {
        $directorId = trim((string)$directorId);
        if ($directorId === '') {
            return;
        }

        $row = Db::name('sys_user')
            ->where('ID', $directorId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();
        if (!$row) {
            throw new RuntimeException('director not found', 404);
        }

        $userTenantId = trim((string)($row['TENANT_ID'] ?? ''));
        if ($tenantId !== '' && $tenantId !== '0' && $userTenantId !== '' && $userTenantId !== '0' && $tenantId !== $userTenantId) {
            throw new RuntimeException('tenant mismatch', 403);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     */
    private function ensureOrgWriteAllowed(array $payload, array $data, bool $bizScope, string $action, ?array $existing): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasOrgPermission($payload, $bizScope, $action)) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds === []) {
            throw new RuntimeException('permission denied', 403);
        }

        if ($action === 'add') {
            if (in_array((string)$data['PARENT_ID'], $scopeOrgIds, true)) {
                return;
            }

            throw new RuntimeException('permission denied', 403);
        }

        $existingId = (string)($existing['ID'] ?? '');
        $existingParentId = (string)($existing['PARENT_ID'] ?? '');
        if ($existingId !== '' && in_array($existingId, $scopeOrgIds, true) && in_array($existingParentId, $scopeOrgIds, true)) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $selectedRows
     */
    private function ensureOrgDeleteAllowed(array $payload, array $selectedRows, bool $bizScope): void
    {
        $admin = $this->isAdminCompatible($payload);
        if (!$admin && !$this->hasOrgPermission($payload, $bizScope, 'delete')) {
            throw new RuntimeException('permission denied', 403);
        }

        if (!$bizScope || $admin) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds === []) {
            throw new RuntimeException('permission denied', 403);
        }

        foreach ($selectedRows as $row) {
            if (!in_array((string)($row['ID'] ?? ''), $scopeOrgIds, true)) {
                throw new RuntimeException('permission denied', 403);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasOrgPermission(array $payload, bool $bizScope, string $action): bool
    {
        $action = strtolower($action);
        $codes = array_merge(
            $this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []),
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            $this->stringList($payload['button_codes'] ?? $payload['buttonCodeList'] ?? [])
        );
        $needles = $bizScope
            ? ["/biz/org/{$action}", "bizorg{$action}"]
            : ["/sys/org/{$action}", "sysorg{$action}"];

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
     * @param array<string, mixed> $org
     */
    private function ensureTenantCompatible(array $payload, array $org): void
    {
        $payloadTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        $orgTenantId = trim((string)($org['TENANT_ID'] ?? ''));
        if ($payloadTenantId !== '' && $orgTenantId !== '' && $payloadTenantId !== $orgTenantId && !$this->isAdminCompatible($payload)) {
            throw new RuntimeException('permission denied', 403);
        }
    }

    /**
     * @param array<int, string> $orgIds
     */
    private function assertOrgDeleteDependencies(array $orgIds): void
    {
        $directUserCount = Db::name('sys_user')
            ->whereIn('ORG_ID', $orgIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
        if ($directUserCount > 0) {
            throw new RuntimeException('organization has users', 400);
        }

        if ($this->positionJsonReferencesOrg($orgIds)) {
            throw new RuntimeException('organization has users', 400);
        }

        $roleCount = Db::name('sys_role')
            ->whereIn('ORG_ID', $orgIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
        if ($roleCount > 0) {
            throw new RuntimeException('organization has roles', 400);
        }

        $positionCount = Db::name('sys_position')
            ->whereIn('ORG_ID', $orgIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
        if ($positionCount > 0) {
            throw new RuntimeException('organization has positions', 400);
        }
    }

    /**
     * @param array<int, string> $orgIds
     */
    private function positionJsonReferencesOrg(array $orgIds): bool
    {
        $idMap = array_fill_keys($orgIds, true);
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
            if (json_last_error() === JSON_ERROR_NONE && $this->valueContainsOrgId($decoded, $idMap)) {
                return true;
            }

            foreach ($orgIds as $orgId) {
                if ($orgId !== '' && str_contains($json, $orgId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, bool> $idMap
     */
    private function valueContainsOrgId(mixed $value, array $idMap): bool
    {
        if (is_scalar($value) && isset($idMap[(string)$value])) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (in_array((string)$key, ['orgId', 'org_id', 'ORG_ID'], true) && isset($idMap[(string)$item])) {
                return true;
            }
            if ($this->valueContainsOrgId($item, $idMap)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $rootIds
     * @return array<int, string>
     */
    private function orgAndChildren(array $rootIds): array
    {
        $rootIds = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $rootIds))));
        if ($rootIds === []) {
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
        $queue = $rootIds;
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
     * @param array<int, mixed> $value
     * @return array<int, string>
     */
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
                $id = trim((string)($item['id'] ?? $item['orgId'] ?? $item['value'] ?? $item['key'] ?? ''));
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

    private function newOrgCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $exists = Db::name('sys_org')
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function orgRow(array $row): array
    {
        return array_merge($row, [
            'id' => $row['ID'] ?? null,
            'parentId' => $row['PARENT_ID'] ?? null,
            'name' => $row['NAME'] ?? null,
            'category' => $row['CATEGORY'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'directorId' => $row['DIRECTOR_ID'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => $row['TENANT_ID'] ?? null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTree(array $nodes): array
    {
        return array_map(function (array $node): array {
            $children = $node['children'] ?? [];
            $row = $this->orgRow($node);
            $row['children'] = is_array($children) ? $this->normalizeTree($children) : [];

            return $row;
        }, $nodes);
    }
}
