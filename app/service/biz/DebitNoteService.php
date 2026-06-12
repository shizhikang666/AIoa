<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only debit-note queries compatible with Java BizDebitNoteController.
 */
class DebitNoteService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ALREADY_SETTLED = 'AlreadySettled';
    private const NOTE_FIELDS = <<<SQL
d.ID AS ID,
d.EXPENDITURE_RECORD_ID AS EXPENDITURE_RECORD_ID,
d.REMARK AS REMARK,
d.PLAY_STATUS AS PLAY_STATUS,
d.AMOUNT AS AMOUNT,
d.SETTLEMENT_AMOUNT AS SETTLEMENT_AMOUNT,
d.DELETE_FLAG AS DELETE_FLAG,
d.CREATE_TIME AS CREATE_TIME,
d.CREATE_USER AS CREATE_USER,
d.UPDATE_TIME AS UPDATE_TIME,
d.UPDATE_USER AS UPDATE_USER,
d.TENANT_ID AS TENANT_ID,
d.VERSION AS VERSION,
d.ORG AS ORG,
d.HISTORY_AMOUNT AS HISTORY_AMOUNT,
e.PAYER_TIME AS PAYER_TIME,
e.TARGET_ID AS ACCOUNT_ID,
e.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
e.PAYER AS PAYER,
e.BANK_NAME AS BANK_NAME,
e.BANK_ACCOUNT AS BANK_ACCOUNT,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'd.ID',
        'expenditureRecordId' => 'd.EXPENDITURE_RECORD_ID',
        'remark' => 'd.REMARK',
        'playStatus' => 'd.PLAY_STATUS',
        'amount' => 'd.AMOUNT',
        'settlementAmount' => 'd.SETTLEMENT_AMOUNT',
        'historyAmount' => 'd.HISTORY_AMOUNT',
        'createTime' => 'd.CREATE_TIME',
        'updateTime' => 'd.UPDATE_TIME',
        'tenantId' => 'd.TENANT_ID',
        'version' => 'd.VERSION',
        'org' => 'd.ORG',
        'payerTime' => 'e.PAYER_TIME',
        'category' => 'e.SETTLEMENT_CATEGORY',
        'settlementCategory' => 'e.SETTLEMENT_CATEGORY',
        'accountName' => 'a.ACCOUNT_NAME',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->noteQuery($filters, $payload)->count();
        $rows = $this->applySort($this->noteQuery($filters, $payload), $filters)
            ->field(self::NOTE_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->noteRows($rows),
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
        $rows = $this->applySort($this->noteQuery($filters, $payload), $filters)
            ->field(self::NOTE_FIELDS)
            ->select()
            ->toArray();

        return $this->noteRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->noteQuery(['id' => $id], $payload)
            ->field(self::NOTE_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('debit note not found', 404);
        }

        return $this->noteRows([$row])[0];
    }

    public function markSuccess(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $payload): array {
            $this->assertNoteWritable($id, $payload, 'mark this debit note settled');
            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_debit_note')
                ->where('ID', $id)
                ->update([
                    'PLAY_STATUS' => self::ALREADY_SETTLED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return ['id' => $id, 'count' => $updated];
        });
    }

    private function noteQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_debit_note')
            ->alias('d')
            ->leftJoin('biz_expenditure_record e', 'e.ID = d.EXPENDITURE_RECORD_ID')
            ->leftJoin('settlement_account a', 'a.ID = e.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = d.ORG')
            ->where(function ($query): void {
                $query->whereNull('d.DELETE_FLAG')->whereOr('d.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('d.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'd.ID',
            'expenditureRecordId' => 'd.EXPENDITURE_RECORD_ID',
            'playStatus' => 'd.PLAY_STATUS',
            'org' => 'd.ORG',
            'orgId' => 'd.ORG',
            'category' => 'e.SETTLEMENT_CATEGORY',
            'settlementCategory' => 'e.SETTLEMENT_CATEGORY',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['remark'])) {
            $query->whereLike('d.REMARK', '%' . trim((string)$filters['remark']) . '%');
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        if (!empty($filters['amount'])) {
            $query->whereLike('d.AMOUNT', '%' . trim((string)$filters['amount']) . '%');
        }

        if (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('d.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('d.REMARK', $keyword)
                    ->whereOr('a.ACCOUNT_NAME', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NUMBER', 'like', $keyword)
                    ->whereOr('e.PAYER', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        $this->applyTimeRange($query, $filters, 'd.CREATE_TIME', 'startCreateTime', 'endCreateTime');

        return $query;
    }

    private function assertNoteWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeNote($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $noteOrg = trim((string)($row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $noteOrg !== '' && in_array($noteOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    private function activeNote(string $id, array $payload): array
    {
        $query = Db::name('biz_debit_note')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('ID,CREATE_USER,TENANT_ID,ORG')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('debit note not found', 404);
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

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('d.ID', 'asc');
        }

        return $query->order('d.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function noteRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->noteRow($row), $rows);
    }

    private function noteRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'expenditureRecordId' => $this->value($row, 'EXPENDITURE_RECORD_ID', 'expenditureRecordId'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'playStatus' => $this->value($row, 'PLAY_STATUS', 'playStatus'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'settlementAmount' => $this->decimal($this->value($row, 'SETTLEMENT_AMOUNT', 'settlementAmount')),
            'historyAmount' => $this->decimal($this->value($row, 'HISTORY_AMOUNT', 'historyAmount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => (int)$this->value($row, 'VERSION', 'version'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
            'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
            'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
            'category' => $this->value($row, 'SETTLEMENT_CATEGORY', 'category'),
            'payer' => $this->value($row, 'PAYER', 'payer'),
            'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
            'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
        ];
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

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
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
