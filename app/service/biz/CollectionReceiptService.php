<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only collection-receipt queries compatible with Java BizCollectionReceiptController.
 */
class CollectionReceiptService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const ALREADY_SETTLED = 'AlreadySettled';
    private const RECEIPT_FIELDS = <<<SQL
c.ID AS ID,
c.PAYMENT_RECORD_ID AS PAYMENT_RECORD_ID,
c.REMARK AS REMARK,
c.PLAY_STATUS AS PLAY_STATUS,
c.AMOUNT AS AMOUNT,
c.SETTLEMENT_AMOUNT AS SETTLEMENT_AMOUNT,
c.DELETE_FLAG AS DELETE_FLAG,
c.CREATE_TIME AS CREATE_TIME,
c.CREATE_USER AS CREATE_USER,
c.UPDATE_TIME AS UPDATE_TIME,
c.UPDATE_USER AS UPDATE_USER,
c.TENANT_ID AS TENANT_ID,
c.VERSION AS VERSION,
p.PAYER_TIME AS PAYER_TIME,
p.TARGET_ID AS ACCOUNT_ID,
p.SETTLEMENT_CATEGORY AS SETTLEMENT_CATEGORY,
p.PAYER AS PAYER,
p.BANK_NAME AS BANK_NAME,
p.BANK_ACCOUNT AS BANK_ACCOUNT,
p.ORG AS ORG,
a.ACCOUNT_NAME AS ACCOUNT_NAME,
a.ACCOUNT_NUMBER AS ACCOUNT_NUMBER,
org.NAME AS ORG_NAME
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'c.ID',
        'paymentRecordId' => 'c.PAYMENT_RECORD_ID',
        'remark' => 'c.REMARK',
        'playStatus' => 'c.PLAY_STATUS',
        'amount' => 'c.AMOUNT',
        'settlementAmount' => 'c.SETTLEMENT_AMOUNT',
        'createTime' => 'c.CREATE_TIME',
        'updateTime' => 'c.UPDATE_TIME',
        'tenantId' => 'c.TENANT_ID',
        'version' => 'c.VERSION',
        'payerTime' => 'p.PAYER_TIME',
        'accountName' => 'a.ACCOUNT_NAME',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->receiptQuery($filters, $payload)->count();
        $rows = $this->applySort($this->receiptQuery($filters, $payload), $filters)
            ->field(self::RECEIPT_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->receiptRows($rows),
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
        $rows = $this->applySort($this->receiptQuery($filters, $payload), $filters)
            ->field(self::RECEIPT_FIELDS)
            ->select()
            ->toArray();

        return $this->receiptRows($rows);
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->receiptQuery(['id' => $id], $payload)
            ->field(self::RECEIPT_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('collection receipt not found', 404);
        }

        return $this->receiptRows([$row])[0];
    }

    public function markSuccess(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $payload): array {
            $this->assertReceiptWritable($id, $payload, 'mark this collection receipt settled');
            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_collection_receipt')
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

    private function receiptQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_collection_receipt')
            ->alias('c')
            ->leftJoin('biz_payment_record p', 'p.ID = c.PAYMENT_RECORD_ID')
            ->leftJoin('settlement_account a', 'a.ID = p.TARGET_ID')
            ->leftJoin('sys_org org', 'org.ID = p.ORG')
            ->where(function ($query): void {
                $query->whereNull('c.DELETE_FLAG')->whereOr('c.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'c.ID',
            'paymentRecordId' => 'c.PAYMENT_RECORD_ID',
            'playStatus' => 'c.PLAY_STATUS',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['remark'])) {
            $query->whereLike('c.REMARK', '%' . trim((string)$filters['remark']) . '%');
        }

        if (!empty($filters['accountName'])) {
            $query->whereLike('a.ACCOUNT_NAME', '%' . trim((string)$filters['accountName']) . '%');
        }

        if (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('p.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('c.REMARK', $keyword)
                    ->whereOr('a.ACCOUNT_NAME', 'like', $keyword)
                    ->whereOr('a.ACCOUNT_NUMBER', 'like', $keyword)
                    ->whereOr('p.PAYER', 'like', $keyword)
                    ->whereOr('org.NAME', 'like', $keyword);
            });
        }

        return $query;
    }

    private function assertReceiptWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeReceipt($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $receiptOrg = trim((string)($row['PAYMENT_ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $receiptOrg !== '' && in_array($receiptOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    private function activeReceipt(string $id, array $payload): array
    {
        $query = Db::name('biz_collection_receipt')
            ->alias('c')
            ->leftJoin('biz_payment_record p', 'p.ID = c.PAYMENT_RECORD_ID')
            ->where('c.ID', $id)
            ->where(function ($query): void {
                $query->whereNull('c.DELETE_FLAG')->whereOr('c.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('c.TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('c.ID,c.CREATE_USER,c.TENANT_ID,p.ORG AS PAYMENT_ORG')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('collection receipt not found', 404);
        }

        return $row;
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('c.ID', 'asc');
        }

        return $query->order('c.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function receiptRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->receiptRow($row), $rows);
    }

    private function receiptRow(array $row): array
    {
        return [
            'id' => $this->value($row, 'ID', 'id'),
            'paymentRecordId' => $this->value($row, 'PAYMENT_RECORD_ID', 'paymentRecordId'),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'playStatus' => $this->value($row, 'PLAY_STATUS', 'playStatus'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'settlementAmount' => $this->decimal($this->value($row, 'SETTLEMENT_AMOUNT', 'settlementAmount')),
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => (int)$this->value($row, 'VERSION', 'version'),
            'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
            'accountId' => $this->value($row, 'ACCOUNT_ID', 'accountId'),
            'accountName' => $this->value($row, 'ACCOUNT_NAME', 'accountName'),
            'accountNumber' => $this->value($row, 'ACCOUNT_NUMBER', 'accountNumber'),
            'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
            'payer' => $this->value($row, 'PAYER', 'payer'),
            'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
            'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
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
