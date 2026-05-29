<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only settlement-account queries compatible with Java SettlementAccountController.
 */
class SettlementAccountService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ENABLE = 'ENABLE';
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
