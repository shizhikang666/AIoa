<?php

declare(strict_types=1);

namespace app\service\dev;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only configuration queries with sensitive value masking.
 */
class ConfigService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SYS_BASE = 'SYS_BASE';
    private const BIZ_DEFINE = 'BIZ_DEFINE';
    private const DEFAULT_PASSWORD_KEY = 'SNOWY_SYS_DEFAULT_PASSWORD';
    private const MASK = '******';
    private const SORT_FIELD_MAP = [
        'id' => 'ID',
        'configKey' => 'CONFIG_KEY',
        'configValue' => 'CONFIG_VALUE',
        'category' => 'CATEGORY',
        'remark' => 'REMARK',
        'sortCode' => 'SORT_CODE',
        'createTime' => 'CREATE_TIME',
        'updateTime' => 'UPDATE_TIME',
    ];

    public function page(array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $filters['category'] = self::BIZ_DEFINE;
        $total = $this->configQuery($filters)->count();
        $rows = $this->applySort($this->configQuery($filters), $filters)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->configRow($row), $rows),
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
    public function sysBaseList(): array
    {
        $rows = $this->configQuery(['category' => self::SYS_BASE])
            ->where('CONFIG_KEY', '<>', self::DEFAULT_PASSWORD_KEY)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->configRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $rows = $this->configQuery($filters)
            ->order(['SORT_CODE' => 'asc', 'ID' => 'asc'])
            ->select()
            ->toArray();

        return array_map(fn (array $row): array => $this->configRow($row), $rows);
    }

    public function detail(string $id): ?array
    {
        $row = $this->configQuery(['id' => $id])->find();
        if (!$row) {
            return null;
        }

        return $this->configRow(is_array($row) ? $row : $row->toArray());
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function add(array $input, array $payload = []): array
    {
        $data = $this->writeData($input, null, $payload);
        $this->assertUniqueConfigKey($data['CONFIG_KEY'], null);
        Db::name('dev_config')->insert($data);

        return $this->configRow($data);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, ['id', 'ID'], 'id');
        $existing = $this->activeConfigRow($id);
        if ((string)($existing['CATEGORY'] ?? '') !== self::BIZ_DEFINE) {
            throw new RuntimeException('system config cannot be edited here', 400);
        }

        $data = $this->writeData($input, $existing, $payload);
        $this->assertUniqueConfigKey($data['CONFIG_KEY'], $id);
        Db::name('dev_config')
            ->where('ID', $id)
            ->where('CATEGORY', self::BIZ_DEFINE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->update($data);

        return $this->configRow($this->activeConfigRow($id));
    }

    /**
     * @param array<int, string> $ids
     * @param array<string, mixed> $payload
     */
    public function delete(array $ids, array $payload = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $ids))));
        if ($ids === []) {
            throw new RuntimeException('missing idList', 400);
        }

        $rows = $this->activeConfigRowsByIds($ids);
        if (count($rows) !== count($ids)) {
            throw new RuntimeException('config not found', 404);
        }
        foreach ($rows as $row) {
            if ((string)($row['CATEGORY'] ?? '') !== self::BIZ_DEFINE) {
                throw new RuntimeException('system config cannot be deleted', 400);
            }
        }

        Db::name('dev_config')
            ->whereIn('ID', $ids)
            ->where('CATEGORY', self::BIZ_DEFINE)
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

    /**
     * @param array<string|int, mixed> $input
     * @param array<string, mixed> $payload
     */
    public function editBatch(array $input, array $payload = []): ?array
    {
        $items = $this->batchItems($input);
        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid config batch item', 400);
            }

            $configKey = $this->requiredInput($item, ['configKey', 'CONFIG_KEY'], 'configKey');
            if (isset($seen[$configKey])) {
                throw new RuntimeException('duplicate configKey', 400);
            }

            $seen[$configKey] = true;
            $normalized[] = [
                'configKey' => $configKey,
                'configValue' => $this->requiredInput($item, ['configValue', 'CONFIG_VALUE'], 'configValue'),
            ];
        }

        $keys = array_column($normalized, 'configKey');
        $rows = $this->activeConfigRowsByKeys($keys);
        if (count($rows) !== count($keys)) {
            throw new RuntimeException('config not found', 404);
        }
        foreach ($keys as $key) {
            if (!isset($rows[$key])) {
                throw new RuntimeException('config not found', 404);
            }
        }

        $now = date('Y-m-d H:i:s');
        $userId = $this->payloadUserId($payload);
        Db::transaction(function () use ($normalized, $rows, $now, $userId): void {
            foreach ($normalized as $item) {
                $configKey = (string)$item['configKey'];
                $row = $rows[$configKey];
                $configValue = (string)$item['configValue'];
                if ($this->isSensitiveKey($configKey) && $configValue === self::MASK) {
                    $configValue = (string)($row['CONFIG_VALUE'] ?? '');
                }

                Db::name('dev_config')
                    ->where('ID', (string)$row['ID'])
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->update([
                        'CONFIG_VALUE' => $configValue,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId,
                    ]);
            }
        });

        return null;
    }

    private function configQuery(array $filters)
    {
        $query = Db::name('dev_config')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if (!empty($filters['id'])) {
            $query->where('ID', (string)$filters['id']);
        }

        if (!empty($filters['category'])) {
            $query->where('CATEGORY', (string)$filters['category']);
        }

        if (!empty($filters['configKey'])) {
            $query->where('CONFIG_KEY', (string)$filters['configKey']);
        }

        if (!empty($filters['searchKey'])) {
            $query->whereLike('CONFIG_KEY', '%' . trim((string)$filters['searchKey']) . '%');
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

        return $query->order(['SORT_CODE' => 'asc', 'ID' => 'asc']);
    }

    private function configRow(array $row): array
    {
        $key = (string)($row['CONFIG_KEY'] ?? '');
        $value = $row['CONFIG_VALUE'] ?? null;
        $sensitive = $this->isSensitiveKey($key);

        return [
            'id' => $row['ID'] ?? null,
            'configKey' => $key,
            'configValue' => $sensitive && $value !== null && $value !== '' ? self::MASK : $value,
            'category' => $row['CATEGORY'] ?? null,
            'remark' => $row['REMARK'] ?? null,
            'sortCode' => $row['SORT_CODE'] ?? null,
            'extJson' => $row['EXT_JSON'] ?? null,
            'sensitive' => $sensitive,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function writeData(array $input, ?array $existing, array $payload): array
    {
        $configKey = $this->requiredInput($input, ['configKey', 'CONFIG_KEY'], 'configKey');
        $configValue = $this->requiredInput($input, ['configValue', 'CONFIG_VALUE'], 'configValue');
        if ($existing !== null && $this->isSensitiveKey($configKey) && $configValue === self::MASK) {
            $configValue = (string)($existing['CONFIG_VALUE'] ?? '');
        }

        $sortCodeValue = $input['sortCode'] ?? $input['SORT_CODE'] ?? null;
        if ($sortCodeValue === null || trim((string)$sortCodeValue) === '') {
            throw new RuntimeException('missing sortCode', 400);
        }

        $now = date('Y-m-d H:i:s');
        $userId = $this->payloadUserId($payload);
        $data = [
            'CONFIG_KEY' => $configKey,
            'CONFIG_VALUE' => $configValue,
            'CATEGORY' => self::BIZ_DEFINE,
            'REMARK' => $this->optionalString($input, ['remark', 'REMARK']),
            'SORT_CODE' => (int)$sortCodeValue,
            'EXT_JSON' => $this->optionalString($input, ['extJson', 'EXT_JSON']),
            'UPDATE_TIME' => $now,
            'UPDATE_USER' => $userId,
        ];

        if ($existing === null) {
            $data['ID'] = $this->newId();
            $data['DELETE_FLAG'] = self::NOT_DELETE;
            $data['CREATE_TIME'] = $now;
            $data['CREATE_USER'] = $userId;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function requiredInput(array $input, array $keys, string $name): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = trim((string)$input[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        throw new RuntimeException("missing {$name}", 400);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private function optionalString(array $input, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                $value = (string)$input[$key];

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private function assertUniqueConfigKey(string $configKey, ?string $excludeId): void
    {
        $query = Db::name('dev_config')
            ->where('CONFIG_KEY', $configKey)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($excludeId !== null && $excludeId !== '') {
            $query->where('ID', '<>', $excludeId);
        }
        if ((int)$query->count() > 0) {
            throw new RuntimeException('duplicate configKey', 400);
        }
    }

    private function activeConfigRow(string $id): array
    {
        $row = $this->configQuery(['id' => $id])->find();
        if (!$row) {
            throw new RuntimeException('config not found', 404);
        }

        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function activeConfigRowsByIds(array $ids): array
    {
        $rows = Db::name('dev_config')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $byId = [];
        foreach ($rows as $row) {
            $byId[(string)($row['ID'] ?? '')] = $row;
        }

        return $byId;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, array<string, mixed>>
     */
    private function activeConfigRowsByKeys(array $keys): array
    {
        $rows = Db::name('dev_config')
            ->whereIn('CONFIG_KEY', $keys)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->select()
            ->toArray();

        $byKey = [];
        foreach ($rows as $row) {
            $key = (string)($row['CONFIG_KEY'] ?? '');
            if ($key === '' || isset($byKey[$key])) {
                throw new RuntimeException('duplicate configKey', 400);
            }

            $byKey[$key] = $row;
        }

        return $byKey;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, mixed>
     */
    private function batchItems(array $input): array
    {
        if (isset($input[0])) {
            return array_values($input);
        }

        foreach (['list', 'items', 'configs', 'configList'] as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if (!is_array($value) || $value === []) {
                    throw new RuntimeException('missing config batch', 400);
                }

                return array_values($value);
            }
        }

        throw new RuntimeException('missing config batch', 400);
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtoupper($key);

        return preg_match('/(PASSWORD|SECRET|TOKEN|PRIVATE|ACCESS_KEY|APP_KEY)/', $key) === 1;
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
