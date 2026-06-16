<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Expenditure-record queries and narrow correction compatible with Java BizExpenditureRecordController.
 */
class ExpenditureRecordService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const CURRENT_CATEGORY_LOCKED = ['ReturnAndRefund', 'GOODS_EXPENDITURE', 'repayment'];
    private const TARGET_CATEGORY_LOCKED = ['CUSTOMER_REBATE', 'ReturnAndRefund', 'repayment', 'TravelExpenses'];
    private const RECORD_FIELDS = <<<SQL
e.ID AS ID,
e.OBJECT_ID AS OBJECT_ID,
e.TARGET_ID AS TARGET_ID,
e.SERIAL_ID AS SERIAL_ID,
e.PROCESS_ID AS PROCESS_ID,
e.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
e.PAYER AS PAYER,
e.BANK_NAME AS BANK_NAME,
e.BANK_ACCOUNT AS BANK_ACCOUNT,
e.REMARK AS REMARK,
e.PAYER_TIME AS PAYER_TIME,
e.AMOUNT AS AMOUNT,
e.DELETE_FLAG AS DELETE_FLAG,
e.CREATE_TIME AS CREATE_TIME,
e.CREATE_USER AS CREATE_USER,
e.UPDATE_TIME AS UPDATE_TIME,
e.UPDATE_USER AS UPDATE_USER,
e.TENANT_ID AS TENANT_ID,
e.`USER` AS USER_ID,
e.ORG AS ORG,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'e.ID',
        'objectId' => 'e.OBJECT_ID',
        'targetId' => 'e.TARGET_ID',
        'serialId' => 'e.SERIAL_ID',
        'processId' => 'e.PROCESS_ID',
        'settlementCategory' => 'e.SETTLEMENT_CATEGORY',
        'payer' => 'e.PAYER',
        'bankName' => 'e.BANK_NAME',
        'bankAccount' => 'e.BANK_ACCOUNT',
        'payerTime' => 'e.PAYER_TIME',
        'amount' => 'e.AMOUNT',
        'createTime' => 'e.CREATE_TIME',
        'updateTime' => 'e.UPDATE_TIME',
        'tenantId' => 'e.TENANT_ID',
        'user' => 'e.`USER`',
        'org' => 'e.ORG',
        'accountName' => 'a.ACCOUNT_NAME',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->recordQuery($filters, $payload)->count();
        $rows = $this->applySort($this->recordQuery($filters, $payload), $filters)
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
        $rows = $this->applySort($this->recordQuery($filters, $payload), $filters)
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
        $rows = $this->applySort($this->recordQuery($filters, $payload), $filters)
            ->field(self::RECORD_FIELDS)
            ->select()
            ->toArray();

        return $this->recordRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->recordQuery(['id' => $id], $payload)
            ->field(self::RECORD_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('expenditure record not found', 404);
        }

        return $this->recordRows([$row])[0];
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $payerTime = $this->optionalTime($input, 'payerTime');
        $settlementCategory = $this->optionalString($input, 'settlementCategory');

        return Db::transaction(function () use ($id, $payerTime, $settlementCategory, $payload): array {
            $record = $this->assertRecordWritable($id, $payload, 'edit this expenditure record');
            $this->assertEditableCorrection($record, $settlementCategory);

            $statementId = trim((string)($record['SERIAL_ID'] ?? ''));
            if ($statementId === '') {
                throw new RuntimeException('expenditure record statement missing', 404);
            }
            $statement = $this->activeStatement($statementId, $payload);

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $recordUpdate = [
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
            ];
            if ($payerTime !== null) {
                $recordUpdate['PAYER_TIME'] = $payerTime;
            }
            if ($settlementCategory !== null) {
                $recordUpdate['SETTLEMENT_CATEGORY'] = $settlementCategory;
            }

            $updated = Db::name('biz_expenditure_record')
                ->where('ID', $id)
                ->update($recordUpdate);

            $statementUpdate = [
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
            ];
            if ($payerTime !== null) {
                $statementUpdate['PAYER_TIME'] = $payerTime;
            }

            $statementUpdated = Db::name('settlement_account_statement')
                ->where('ID', (string)$statement['ID'])
                ->update($statementUpdate);

            return [
                'id' => $id,
                'statementId' => (string)$statement['ID'],
                'count' => $updated,
                'statementCount' => $statementUpdated,
            ];
        });
    }

    private function recordQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_expenditure_record')
            ->alias('e')
            ->leftJoin('settlement_account a', 'a.ID = e.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = e.ORG')
            ->where(function ($query): void {
                $query->whereNull('e.DELETE_FLAG')->whereOr('e.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('e.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'e.ID',
            'objectId' => 'e.OBJECT_ID',
            'targetId' => 'e.TARGET_ID',
            'serialId' => 'e.SERIAL_ID',
            'processId' => 'e.PROCESS_ID',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        $objectIds = $this->stringList($filters['objectIds'] ?? []);
        if ($objectIds !== []) {
            $query->whereIn('e.OBJECT_ID', $objectIds);
        }

        $categories = $this->stringList($filters['settlementCategory'] ?? []);
        if ($categories !== []) {
            $query->whereIn('e.SETTLEMENT_CATEGORY', $categories);
        }

        foreach ([
            'payer' => 'e.PAYER',
            'bankName' => 'e.BANK_NAME',
            'bankAccount' => 'e.BANK_ACCOUNT',
            'remark' => 'e.REMARK',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->whereLike($column, '%' . trim((string)$filters[$filter]) . '%');
            }
        }

        if (!empty($filters['amount'])) {
            $query->whereLike('e.AMOUNT', '%' . trim((string)$filters['amount']) . '%');
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('e.ORG', $orgIds);
            }
        } elseif (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('e.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('e.REMARK', $keyword)
                    ->whereOr('e.PAYER', 'like', $keyword)
                    ->whereOr('e.BANK_NAME', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NAME', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'e.PAYER_TIME', 'startPayerTime', 'endPayerTime');
        $this->applyTimeRange($query, $filters, 'e.PAYER_TIME', 'payerStartTime', 'payerEndTime');
        $this->applyTimeRange($query, $filters, 'e.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function assertRecordWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeRecord($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $recordOrg = trim((string)($row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $recordOrg !== '' && in_array($recordOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $recordUser = trim((string)($row['USER_ID'] ?? ''));
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && ($recordUser === $currentUserId || $createUser === $currentUserId)) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    private function assertEditableCorrection(array $record, ?string $targetCategory): void
    {
        $objectId = trim((string)($record['OBJECT_ID'] ?? ''));
        if ($objectId !== '') {
            throw new RuntimeException('linked expenditure records cannot be edited', 400);
        }

        $currentCategory = trim((string)($record['SETTLEMENT_CATEGORY'] ?? ''));
        if ($targetCategory === null || $targetCategory === $currentCategory) {
            return;
        }

        if (in_array($currentCategory, self::CURRENT_CATEGORY_LOCKED, true)) {
            throw new RuntimeException('current expenditure category cannot be changed', 400);
        }

        if (in_array($targetCategory, self::TARGET_CATEGORY_LOCKED, true)) {
            throw new RuntimeException('target expenditure category cannot be used', 400);
        }
    }

    private function activeRecord(string $id, array $payload): array
    {
        $query = Db::name('biz_expenditure_record')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('ID,OBJECT_ID,TARGET_ID,SERIAL_ID,PROCESS_ID,SETTLEMENT_CATEGORY,PAYER_TIME,AMOUNT,TENANT_ID,ORG,CREATE_USER,`USER` AS USER_ID')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('expenditure record not found', 404);
        }

        return $row;
    }

    private function activeStatement(string $id, array $payload): array
    {
        $query = Db::name('settlement_account_statement')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->field('ID,TENANT_ID')->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('expenditure record statement not found', 404);
        }

        return $row;
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('e.ID', 'asc');
        }

        return $query->order('e.ID', 'asc');
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function optionalTime(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = trim((string)$input[$key]);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function optionalString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = trim((string)$input[$key]);

        return $value === '' ? null : $value;
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

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
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
