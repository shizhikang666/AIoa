<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only payment-record queries compatible with Java BizPaymentRecordController.
 */
class PaymentRecordService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const RECORD_FIELDS = <<<SQL
r.ID AS ID,
r.OBJECT_ID AS OBJECT_ID,
r.TARGET_ID AS TARGET_ID,
r.SERIAL_ID AS SERIAL_ID,
r.PROCESS_ID AS PROCESS_ID,
r.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
r.PAYER AS PAYER,
r.BANK_NAME AS BANK_NAME,
r.BANK_ACCOUNT AS BANK_ACCOUNT,
r.REMARK AS REMARK,
r.PAYER_TIME AS PAYER_TIME,
r.AMOUNT AS AMOUNT,
r.DELETE_FLAG AS DELETE_FLAG,
r.CREATE_TIME AS CREATE_TIME,
r.CREATE_USER AS CREATE_USER,
r.UPDATE_TIME AS UPDATE_TIME,
r.UPDATE_USER AS UPDATE_USER,
r.TENANT_ID AS TENANT_ID,
r.`USER` AS USER_ID,
r.ORG AS ORG,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'r.ID',
        'objectId' => 'r.OBJECT_ID',
        'targetId' => 'r.TARGET_ID',
        'serialId' => 'r.SERIAL_ID',
        'processId' => 'r.PROCESS_ID',
        'settlementCategory' => 'r.SETTLEMENT_CATEGORY',
        'payer' => 'r.PAYER',
        'bankName' => 'r.BANK_NAME',
        'bankAccount' => 'r.BANK_ACCOUNT',
        'payerTime' => 'r.PAYER_TIME',
        'amount' => 'r.AMOUNT',
        'createTime' => 'r.CREATE_TIME',
        'updateTime' => 'r.UPDATE_TIME',
        'tenantId' => 'r.TENANT_ID',
        'user' => 'r.`USER`',
        'org' => 'r.ORG',
        'accountName' => 'a.ACCOUNT_NAME',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->recordQuery($filters, $payload, true)->count();
        $rows = $this->applySort($this->recordQuery($filters, $payload, true), $filters)
            ->field(self::RECORD_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->recordRows($rows),
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
    public function listDetails(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->recordQuery($filters, $payload, false), $filters)
            ->field(self::RECORD_FIELDS)
            ->select()
            ->toArray();

        return $this->recordRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->recordQuery($filters, $payload, false), $filters)
            ->field(self::RECORD_FIELDS)
            ->select()
            ->toArray();

        return $this->recordRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->recordQuery(['id' => $id], $payload, false)
            ->field(self::RECORD_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('payment record not found', 404);
        }

        return $this->recordRows([$row])[0];
    }

    private function recordQuery(array $filters, array $payload, bool $prefixSettlementCategory)
    {
        $query = Db::name('biz_payment_record')
            ->alias('r')
            ->leftJoin('settlement_account a', 'a.ID = r.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = r.ORG')
            ->where(function ($query): void {
                $query->whereNull('r.DELETE_FLAG')->whereOr('r.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('r.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'r.ID',
            'objectId' => 'r.OBJECT_ID',
            'targetId' => 'r.TARGET_ID',
            'serialId' => 'r.SERIAL_ID',
            'processId' => 'r.PROCESS_ID',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        $objectIds = $this->stringList($filters['objectIds'] ?? []);
        if ($objectIds !== []) {
            $query->whereIn('r.OBJECT_ID', $objectIds);
        }

        $categories = $this->stringList($filters['settlementCategory'] ?? []);
        if ($categories !== []) {
            $query->where(function ($query) use ($categories, $prefixSettlementCategory): void {
                $query->whereIn('r.SETTLEMENT_CATEGORY', $categories);
                if ($prefixSettlementCategory) {
                    foreach ($categories as $category) {
                        $query->whereOr('r.SETTLEMENT_CATEGORY', 'like', $category . '%');
                    }
                }
            });
        }

        if (!empty($filters['amount'])) {
            $query->where('r.AMOUNT', (float)$filters['amount']);
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('r.ORG', $orgIds);
            }
        } elseif (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('r.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('r.REMARK', $keyword)
                    ->whereOr('r.PAYER', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NAME', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'r.PAYER_TIME', 'startPayerTime', 'endPayerTime');
        $this->applyTimeRange($query, $filters, 'r.PAYER_TIME', 'payerStartTime', 'payerEndTime');
        $this->applyTimeRange($query, $filters, 'r.CREATE_TIME', 'startCreateTime', 'endCreateTime');

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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('r.ID', 'asc');
        }

        return $query->order('r.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function recordRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->recordRow($row), $rows);
    }

    private function recordRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
            'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'serialId' => $this->value($row, 'SERIAL_ID', 'serialId'),
            'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
            'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
            'payer' => $this->value($row, 'PAYER', 'payer'),
            'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
            'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'user' => $this->value($row, 'USER_ID', 'user'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
        ];
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
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
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
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($item): string => trim((string)$item), $value)));
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
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
