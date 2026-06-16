<?php

declare(strict_types=1);

namespace app\service\tenant;

use RuntimeException;
use think\facade\Db;

/**
 * Tenant table queries and safe metadata writes compatible with Java TenantsController.
 */
class TenantsService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SYSTEM_CODE = 'tenant';
    private const SORT_FIELD_MAP = [
        'tenantId' => 'Tenant_ID',
        'tenantName' => 'Tenant_Name',
        'code' => 'CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->tenantQuery($filters)->count();
        $rows = $this->applySort($this->tenantQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->tenantRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function detail(string $tenantId): ?array
    {
        $row = $this->tenantQuery(['tenantId' => $tenantId])->find();
        if (!$row) {
            return null;
        }

        return $this->tenantRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function add(array $input, array $payload = []): ?array
    {
        $tenantName = $this->requiredString($input, 'tenantName', 100);
        $this->assertUniqueTenantName($tenantName);

        $now = date('Y-m-d H:i:s');
        Db::name('tenants')->insert([
            'Tenant_ID' => $this->newId(),
            'Tenant_Name' => $tenantName,
            'CODE' => $this->randomCode(),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $this->payloadUserId($payload),
        ]);

        return null;
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): ?array
    {
        $tenantId = $this->requiredString($input, 'tenantId', 20);
        $tenantName = $this->requiredString($input, 'tenantName', 100);
        $row = $this->activeTenantRow($tenantId);
        $this->assertNotSystemTenant($row);
        $this->assertUniqueTenantName($tenantName, $tenantId);

        Db::name('tenants')
            ->where('Tenant_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'Tenant_Name' => $tenantName,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->payloadUserId($payload),
            ]);

        return null;
    }

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function delete(array $input, array $payload = []): ?array
    {
        $ids = $this->deleteIds($input);
        if ($ids === []) {
            throw new RuntimeException('missing tenant id', 400);
        }

        $rows = $this->activeTenantRows($ids);
        foreach ($rows as $row) {
            $this->assertNotSystemTenant($row);
        }
        $this->assertNoTenantReferences($ids);

        Db::name('tenants')
            ->whereIn('Tenant_ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update([
                'DELETE_FLAG' => self::DELETED,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $this->payloadUserId($payload),
            ]);

        return null;
    }

    private function tenantQuery(array $filters)
    {
        $query = Db::name('tenants')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('Tenant_ID', (string)$filters['id']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('Tenant_ID', (string)$filters['tenantId']);
        }

        if (!empty($filters['tenantName'])) {
            $query->whereLike('Tenant_Name', '%' . trim((string)$filters['tenantName']) . '%');
        }

        if (!empty($filters['code'])) {
            $query->whereLike('CODE', '%' . trim((string)$filters['code']) . '%');
        }

        if (!empty($filters['searchKey'])) {
            $searchKey = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('Tenant_Name', $searchKey)
                    ->whereOr('CODE', 'like', $searchKey);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('Tenant_ID', 'asc');
        }

        return $query->order('Tenant_ID', 'asc');
    }

    private function tenantRow(array $row): array
    {
        return [
            'tenantId' => $row['Tenant_ID'] ?? null,
            'tenantName' => $row['Tenant_Name'] ?? null,
            'code' => $row['CODE'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function requiredString(array $input, string $field, int $maxLength): string
    {
        $value = $this->fieldValue($input, $field);
        $value = trim((string)$value);
        if ($value === '') {
            throw new RuntimeException('missing ' . $field, 400);
        }
        if (strlen($value) > $maxLength) {
            throw new RuntimeException($field . ' is too long', 400);
        }

        return $value;
    }

    /**
     * @param array<string|int, mixed> $input
     */
    private function fieldValue(array $input, string $field): mixed
    {
        if (array_key_exists($field, $input)) {
            return $input[$field];
        }

        $column = match ($field) {
            'tenantId' => 'Tenant_ID',
            'tenantName' => 'Tenant_Name',
            default => strtoupper((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $field)),
        };

        if (array_key_exists($column, $input)) {
            return $input[$column];
        }

        if ($field === 'tenantId' && array_key_exists('id', $input)) {
            return $input['id'];
        }

        return null;
    }

    private function assertUniqueTenantName(string $tenantName, ?string $excludeId = null): void
    {
        $query = Db::name('tenants')
            ->where('Tenant_Name', $tenantName)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($excludeId !== null && $excludeId !== '') {
            $query->where('Tenant_ID', '<>', $excludeId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('tenant already exists', 400);
        }
    }

    private function activeTenantRow(string $tenantId): array
    {
        $rows = $this->activeTenantRows([$tenantId]);

        return $rows[0];
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, array<string, mixed>>
     */
    private function activeTenantRows(array $ids): array
    {
        $rows = Db::name('tenants')
            ->whereIn('Tenant_ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(string)($row['Tenant_ID'] ?? '')] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw new RuntimeException('tenant not found', 404);
            }
            $ordered[] = $byId[$id];
        }

        return $ordered;
    }

    private function assertNotSystemTenant(array $row): void
    {
        if ((string)($row['CODE'] ?? '') === self::SYSTEM_CODE) {
            throw new RuntimeException('system tenant cannot be changed', 400);
        }
    }

    /**
     * @param array<int, string> $ids
     */
    private function assertNoTenantReferences(array $ids): void
    {
        $tables = Db::query(
            "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'TENANT_ID' AND TABLE_NAME <> 'tenants' ORDER BY TABLE_NAME"
        );

        foreach ($tables as $table) {
            $tableName = (string)($table['TABLE_NAME'] ?? '');
            if ($tableName === '') {
                continue;
            }

            $query = Db::name($tableName)->whereIn('TENANT_ID', $ids);
            if ($this->tableHasColumn($tableName, 'DELETE_FLAG')) {
                $query->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                });
            }

            if ($query->limit(1)->count() > 0) {
                throw new RuntimeException('tenant is still referenced by ' . $tableName, 400);
            }
        }
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        $rows = Db::query(
            'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tableName, $columnName]
        );

        return (int)($rows[0]['total'] ?? 0) > 0;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        $source = null;
        if ($this->isList($input)) {
            $source = $input;
        } elseif (array_key_exists('idList', $input)) {
            $source = $input['idList'];
        } elseif (array_key_exists('ids', $input)) {
            $source = $input['ids'];
        } elseif (array_key_exists('tenantId', $input)) {
            $source = [$input['tenantId']];
        } elseif (array_key_exists('id', $input)) {
            $source = [$input['id']];
        }

        if (is_string($source)) {
            $source = explode(',', $source);
        }
        if (!is_array($source)) {
            return [];
        }

        $ids = [];
        foreach ($source as $item) {
            $id = is_array($item) ? (string)($item['id'] ?? $item['tenantId'] ?? $item['Tenant_ID'] ?? '') : (string)$item;
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            if (strlen($id) > 20) {
                throw new RuntimeException('tenantId is too long', 400);
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function isList(array $items): bool
    {
        if ($items === []) {
            return true;
        }

        return array_keys($items) === range(0, count($items) - 1);
    }

    private function payloadUserId(array $payload): ?string
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));

        return $userId === '' ? null : $userId;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function randomCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= (string)random_int(0, 9);
            }
        } while (
            Db::name('tenants')
                ->where('CODE', $code)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->count() > 0
        );

        return $code;
    }
}
