<?php

declare(strict_types=1);

namespace app\service\dev;

use think\facade\Db;

/**
 * Read-only configuration queries with sensitive value masking.
 */
class ConfigService
{
    private const NOT_DELETE = 'NOT_DELETE';
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

    private function isSensitiveKey(string $key): bool
    {
        $key = strtoupper($key);

        return preg_match('/(PASSWORD|SECRET|TOKEN|PRIVATE|ACCESS_KEY|APP_KEY)/', $key) === 1;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
