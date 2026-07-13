<?php

declare(strict_types=1);

namespace app\service\biz;

use think\facade\Db;

/**
 * Read-only settlement-account statement queries compatible with Java SettlementAccountStatementController.
 */
class SettlementAccountPaymentService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const FIELDS = <<<SQL
s.ID AS ID,
s.ACCOUNT_ID AS ACCOUNT_ID,
s.PROCESS_ID AS PROCESS_ID,
s.AFTER_AMOUNT AS AFTER_AMOUNT,
s.BEFORE_AMOUNT AS BEFORE_AMOUNT,
s.AMOUNT AS AMOUNT,
s.SETTLEMENT_TYPE AS SETTLEMENT_TYPE,
s.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
s.PROCESS_CATEGORY AS PROCESS_CATEGORY,
s.PAYER_TIME AS PAYER_TIME,
s.CREATE_TIME AS CREATE_TIME,
s.DELETE_FLAG AS DELETE_FLAG,
s.CREATE_USER AS CREATE_USER,
s.UPDATE_TIME AS UPDATE_TIME,
s.EXT_JSON AS EXT_JSON,
s.UPDATE_USER AS UPDATE_USER,
s.TENANT_ID AS TENANT_ID,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
a.org AS ORG,
org.NAME AS ORG_NAME,
creator.NAME AS CREATE_USER_NAME,
updater.NAME AS UPDATE_USER_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 's.ID',
        'accountId' => 's.ACCOUNT_ID',
        'processId' => 's.PROCESS_ID',
        'afterAmount' => 's.AFTER_AMOUNT',
        'beforeAmount' => 's.BEFORE_AMOUNT',
        'amount' => 's.AMOUNT',
        'settlementType' => 's.SETTLEMENT_TYPE',
        'settlementCategory' => 's.SETTLEMENT_CATEGORY',
        'processCategory' => 's.PROCESS_CATEGORY',
        'payerTime' => 's.PAYER_TIME',
        'playTime' => 's.PAYER_TIME',
        'createTime' => 's.CREATE_TIME',
        'accountName' => 'a.ACCOUNT_NAME',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->statementQuery($filters, $payload)->count();
        $rows = $this->applySort($this->statementQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->statementRows($rows),
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
        $rows = $this->applySort($this->statementQuery($filters, $payload), $filters)
            ->field(self::FIELDS)
            ->select()
            ->toArray();

        return $this->statementRows($rows);
    }

    private function statementQuery(array $filters, array $payload)
    {
        $query = Db::name('settlement_account_statement')
            ->alias('s')
            ->leftJoin('settlement_account a', 'a.ID = s.ACCOUNT_ID')
            ->leftJoin('sys_org org', 'org.ID = a.org')
            ->leftJoin('sys_user creator', 'creator.ID = s.CREATE_USER')
            ->leftJoin('sys_user updater', 'updater.ID = s.UPDATE_USER')
            ->where(function ($query): void {
                $query->whereNull('s.DELETE_FLAG')->whereOr('s.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('s.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 's.ID',
            'accountId' => 's.ACCOUNT_ID',
            'processId' => 's.PROCESS_ID',
            'settlementType' => 's.SETTLEMENT_TYPE',
            'settlementCategory' => 's.SETTLEMENT_CATEGORY',
            'processCategory' => 's.PROCESS_CATEGORY',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        $this->applyTimeRange($query, $filters, 's.PAYER_TIME', 'startPlayTime', 'endPlayTime');
        $this->applyTimeRange($query, $filters, 's.PAYER_TIME', 'startPayerTime', 'endPayerTime');
        $this->applyTimeRange($query, $filters, 's.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('s.PROCESS_ID', $keyword)
                    ->whereOr('s.SETTLEMENT_TYPE', 'like', $keyword)
                    ->whereOr('s.SETTLEMENT_CATEGORY', 'like', $keyword)
                    ->whereOr('s.PROCESS_CATEGORY', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NAME', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NUMBER', 'like', $keyword);
            });
        }

        return $query;
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('s.ID', 'asc');
        }

        return $query->order('s.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function statementRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->statementRow($row), $rows);
    }

    private function statementRow(array $row): array
    {
        $extJson = (string)($this->value($row, 'EXT_JSON', 'extJson') ?? '');

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'afterAmount' => $this->decimal($this->value($row, 'AFTER_AMOUNT', 'afterAmount')),
            'beforeAmount' => $this->decimal($this->value($row, 'BEFORE_AMOUNT', 'beforeAmount')),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'settlementType' => $this->value($row, 'SETTLEMENT_TYPE', 'settlementType'),
            'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
            'processCategory' => $this->value($row, 'PROCESS_CATEGORY', 'processCategory'),
            'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'createUserName' => $this->value($row, 'CREATE_USER_NAME', 'createUserName'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'updateUserName' => $this->value($row, 'UPDATE_USER_NAME', 'updateUserName'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'extJson' => $extJson,
            'ext' => $this->decodeJsonObject($extJson),
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
