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
    private const DELETED = 'DELETED';
    private const UNSETTLED = 'Unsettled';
    private const ALREADY_SETTLED = 'AlreadySettled';
    private const LOAN_REPAYMENT_CATEGORY = 'LoanRepayment';
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

    public function __construct(private readonly SettlementAccountService $settlementAccountService = new SettlementAccountService())
    {
    }

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

    public function add(array $input, array $payload = []): array
    {
        $expenditureRecordId = $this->requiredInput($input, 'expenditureRecordId');
        $amountCents = $this->positiveMoneyCents($input['amount'] ?? null);
        $settlementAmountCents = array_key_exists('settlementAmount', $input)
            ? $this->nonNegativeMoneyCents($input['settlementAmount'], 'settlementAmount')
            : 0;
        if ($settlementAmountCents > $amountCents) {
            throw new RuntimeException('debit note settlement amount exceeds debit note amount', 400);
        }
        $remark = $this->nullableString($input['remark'] ?? null);

        return Db::transaction(function () use ($input, $payload, $expenditureRecordId, $amountCents, $settlementAmountCents, $remark): array {
            $expenditureRecord = $this->assertExpenditureRecordWritable(
                $this->activeExpenditureRecord($expenditureRecordId, $payload, true),
                $payload,
                'add debit note'
            );
            $this->assertExpenditureRecordUnbound($expenditureRecordId, null, $payload);

            $recordAmountCents = $this->moneyCents($expenditureRecord['AMOUNT'] ?? '0');
            if ($amountCents > $recordAmountCents) {
                throw new RuntimeException('debit note amount exceeds expenditure record amount', 400);
            }

            $accountId = trim((string)($expenditureRecord['TARGET_ID'] ?? ''));
            $account = $this->activeSettlementAccount($accountId, $payload);
            $tenantId = trim((string)($expenditureRecord['TENANT_ID'] ?? ''));
            if ($tenantId === '') {
                $tenantId = $this->tenantId($input, $payload);
            }
            $orgId = trim((string)($account['ORG'] ?? $account['org'] ?? $expenditureRecord['ORG'] ?? ''));
            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $id = $this->newId();

            $count = Db::name('biz_debit_note')->insert([
                'ID' => $id,
                'EXPENDITURE_RECORD_ID' => $expenditureRecordId,
                'REMARK' => $remark,
                'PLAY_STATUS' => $this->playStatusFor($settlementAmountCents, $amountCents),
                'AMOUNT' => $this->moneyFromCents($amountCents),
                'SETTLEMENT_AMOUNT' => $this->moneyFromCents($settlementAmountCents),
                'HISTORY_AMOUNT' => '0.00',
                'ORG' => $orgId !== '' ? $orgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ]);

            return [
                'id' => $id,
                'expenditureRecordId' => $expenditureRecordId,
                'amount' => $this->moneyFromCents($amountCents),
                'settlementAmount' => $this->moneyFromCents($settlementAmountCents),
                'historyAmount' => '0.00',
                'playStatus' => $this->playStatusFor($settlementAmountCents, $amountCents),
                'org' => $orgId,
                'count' => (int)$count,
            ];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $input, $payload): array {
            $note = $this->assertNoteWritable($id, $payload, 'edit this debit note', true);
            $currentAmountCents = $this->moneyCents($note['AMOUNT'] ?? '0');
            $amountChanged = array_key_exists('amount', $input);
            $amountCents = $amountChanged
                ? $this->positiveMoneyCents($input['amount'])
                : $currentAmountCents;
            $currentSettlementCents = $this->moneyCents($note['SETTLEMENT_AMOUNT'] ?? '0');
            $settlementChanged = array_key_exists('settlementAmount', $input);
            $settlementAmountCents = $settlementChanged
                ? $this->nonNegativeMoneyCents($input['settlementAmount'], 'settlementAmount')
                : $currentSettlementCents;
            if ($settlementAmountCents > $amountCents) {
                throw new RuntimeException('debit note settlement amount exceeds debit note amount', 400);
            }

            $historyAmountCents = $this->moneyCents($note['HISTORY_AMOUNT'] ?? '0');
            $markedSettled = trim((string)($note['PLAY_STATUS'] ?? '')) === self::ALREADY_SETTLED;
            $moneyChanged = $amountCents !== $currentAmountCents || $settlementAmountCents !== $currentSettlementCents;
            if (($currentSettlementCents > 0 || $historyAmountCents > 0 || $markedSettled) && $moneyChanged) {
                throw new RuntimeException('settled debit note can only edit remark', 400);
            }

            $playStatus = $moneyChanged
                ? $this->playStatusFor($settlementAmountCents, $amountCents)
                : trim((string)($note['PLAY_STATUS'] ?? ''));
            if ($playStatus === '') {
                $playStatus = $this->playStatusFor($settlementAmountCents, $amountCents);
            }
            $remark = array_key_exists('remark', $input)
                ? $this->nullableString($input['remark'])
                : $this->nullableString($note['REMARK'] ?? null);
            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_debit_note')
                ->where('ID', $id)
                ->update([
                    'REMARK' => $remark,
                    'PLAY_STATUS' => $playStatus,
                    'AMOUNT' => $this->moneyFromCents($amountCents),
                    'SETTLEMENT_AMOUNT' => $this->moneyFromCents($settlementAmountCents),
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return [
                'id' => $id,
                'expenditureRecordId' => $note['EXPENDITURE_RECORD_ID'] ?? null,
                'amount' => $this->moneyFromCents($amountCents),
                'settlementAmount' => $this->moneyFromCents($settlementAmountCents),
                'historyAmount' => $this->moneyFromCents($historyAmountCents),
                'playStatus' => $playStatus,
                'count' => $updated,
            ];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = array_values(array_unique($this->stringList($ids)));
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $notes = $this->lockedNotes($idList, $payload);
            foreach ($idList as $id) {
                $note = $this->assertNoteRowWritable($notes[$id], $payload, 'delete this debit note');
                if (
                    $this->moneyCents($note['SETTLEMENT_AMOUNT'] ?? '0') > 0
                    || $this->moneyCents($note['HISTORY_AMOUNT'] ?? '0') > 0
                    || trim((string)($note['PLAY_STATUS'] ?? '')) === self::ALREADY_SETTLED
                ) {
                    throw new RuntimeException('settled debit note cannot be deleted directly', 400);
                }
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_debit_note')
                ->whereIn('ID', $idList)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
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

    public function batchRepayment(array $input, array $payload = []): array
    {
        $accountId = $this->requiredInput($input, 'accountId');
        $payer = $this->requiredInput($input, 'payer');
        $payerTime = $this->requiredTime($input, 'payerTime');
        $remark = $this->nullableString($input['remark'] ?? null);
        $items = $this->requiredBatchItems($input['items'] ?? null);

        return Db::transaction(function () use ($items, $accountId, $payer, $payerTime, $remark, $payload): array {
            $notes = $this->lockedNotes(array_column($items, 'id'), $payload);
            $prepared = [];

            foreach ($items as $item) {
                $note = $this->assertNoteRowWritable($notes[$item['id']], $payload, 'repay this debit note');
                $noteAmountCents = $this->moneyCents($note['AMOUNT'] ?? '0');
                $settlementBeforeCents = $this->moneyCents($note['SETTLEMENT_AMOUNT'] ?? '0');
                $settlementAfterCents = $settlementBeforeCents + $item['amountCents'];
                if ($settlementAfterCents > $noteAmountCents) {
                    throw new RuntimeException('debit note settlement amount exceeds debit note amount', 400);
                }

                $prepared[] = [
                    'id' => $item['id'],
                    'amount' => $item['amount'],
                    'amountCents' => $item['amountCents'],
                    'settlementBefore' => $this->moneyFromCents($settlementBeforeCents),
                    'settlementAfter' => $this->moneyFromCents($settlementAfterCents),
                    'playStatus' => $settlementAfterCents === $noteAmountCents ? self::ALREADY_SETTLED : self::UNSETTLED,
                ];
            }

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $results = [];
            foreach ($prepared as $item) {
                $payment = $this->settlementAccountService->paymentAdd([
                    'targetId' => $accountId,
                    'settlementCategory' => self::LOAN_REPAYMENT_CATEGORY,
                    'objectId' => $item['id'],
                    'payer' => $payer,
                    'payerTime' => $payerTime,
                    'amount' => $item['amount'],
                    'remark' => $remark,
                ], $payload);

                $updated = Db::name('biz_debit_note')
                    ->where('ID', $item['id'])
                    ->update([
                        'SETTLEMENT_AMOUNT' => $item['settlementAfter'],
                        'PLAY_STATUS' => $item['playStatus'],
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId !== '' ? $userId : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);

                $results[] = [
                    'id' => $item['id'],
                    'amount' => $item['amount'],
                    'settlementAmountBefore' => $item['settlementBefore'],
                    'settlementAmountAfter' => $item['settlementAfter'],
                    'playStatus' => $item['playStatus'],
                    'paymentId' => $payment['id'] ?? null,
                    'statementId' => $payment['statementId'] ?? null,
                    'accountId' => $accountId,
                    'accountCount' => $payment['accountCount'] ?? null,
                    'debitNoteCount' => $updated,
                ];
            }

            return [
                'accountId' => $accountId,
                'settlementCategory' => self::LOAN_REPAYMENT_CATEGORY,
                'payerTime' => $payerTime,
                'count' => count($results),
                'items' => $results,
            ];
        });
    }

    public function historyAdd(array $input, array $payload = []): array
    {
        $accountId = $this->requiredInput($input, 'accountId');
        $remark = $this->requiredInput($input, 'remark');
        $createTime = $this->requiredTime($input, 'createTime');
        $amountCents = $this->positiveMoneyCents($input['amount'] ?? null);
        $historyAmountCents = $this->nonNegativeMoneyCents($input['historyAmount'] ?? null, 'historyAmount');
        if ($historyAmountCents > $amountCents) {
            throw new RuntimeException('debit note settlement amount exceeds debit note amount', 400);
        }

        return Db::transaction(function () use ($input, $payload, $accountId, $remark, $createTime, $amountCents, $historyAmountCents): array {
            $account = $this->assertSettlementAccountRowWritable(
                $this->activeSettlementAccount($accountId, $payload, true),
                $payload,
                'add debit note repayment history'
            );
            $tenantId = trim((string)($account['TENANT_ID'] ?? ''));
            if ($tenantId === '') {
                $tenantId = $this->tenantId($input, $payload);
            }

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $id = $this->newId();
            $amount = $this->moneyFromCents($amountCents);
            $historyAmount = $this->moneyFromCents($historyAmountCents);
            $playStatus = $historyAmountCents === $amountCents ? self::ALREADY_SETTLED : self::UNSETTLED;
            $orgId = trim((string)($account['ORG'] ?? $account['org'] ?? ''));

            $count = Db::name('biz_debit_note')->insert([
                'ID' => $id,
                'EXPENDITURE_RECORD_ID' => null,
                'REMARK' => $remark,
                'PLAY_STATUS' => $playStatus,
                'AMOUNT' => $amount,
                'SETTLEMENT_AMOUNT' => $historyAmount,
                'HISTORY_AMOUNT' => $historyAmount,
                'ORG' => $orgId !== '' ? $orgId : null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $createTime,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ]);

            return [
                'id' => $id,
                'accountId' => $accountId,
                'amount' => $amount,
                'historyAmount' => $historyAmount,
                'settlementAmount' => $historyAmount,
                'playStatus' => $playStatus,
                'org' => $orgId,
                'tenantId' => $tenantId,
                'count' => (int)$count,
            ];
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

    private function assertNoteWritable(string $id, array $payload, string $action, bool $lock = false): array
    {
        $row = $this->activeNote($id, $payload, $lock);
        return $this->assertNoteRowWritable($row, $payload, $action);
    }

    private function assertNoteRowWritable(array $row, array $payload, string $action): array
    {
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

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function lockedNotes(array $ids, array $payload): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $ids
        )));
        sort($ids, SORT_STRING);

        $query = Db::name('biz_debit_note')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,EXPENDITURE_RECORD_ID,REMARK,CREATE_USER,TENANT_ID,ORG,AMOUNT,SETTLEMENT_AMOUNT,HISTORY_AMOUNT,PLAY_STATUS')
            ->order('ID', 'asc')
            ->lock(true)
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['ID'] ?? '')] = $row;
        }

        foreach ($ids as $id) {
            if (!isset($map[$id])) {
                throw new RuntimeException('debit note not found', 404);
            }
        }

        return $map;
    }

    private function activeNote(string $id, array $payload, bool $lock = false): array
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

        if ($lock) {
            $query->lock(true);
        }

        $row = $query
            ->field('ID,EXPENDITURE_RECORD_ID,REMARK,CREATE_USER,TENANT_ID,ORG,AMOUNT,SETTLEMENT_AMOUNT,HISTORY_AMOUNT,PLAY_STATUS')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('debit note not found', 404);
        }

        return $row;
    }

    private function activeExpenditureRecord(string $id, array $payload, bool $lock = false): array
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

        if ($lock) {
            $query->lock(true);
        }

        $row = $query
            ->field('ID,TARGET_ID,TENANT_ID,ORG,CREATE_USER,`USER` AS USER_ID,AMOUNT')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('expenditure record not found', 404);
        }

        return $row;
    }

    private function assertExpenditureRecordWritable(array $row, array $payload, string $action): array
    {
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

        throw new RuntimeException("no permission to {$action} with this expenditure record", 403);
    }

    private function assertExpenditureRecordUnbound(string $expenditureRecordId, ?string $ignoreId, array $payload): void
    {
        $query = Db::name('biz_debit_note')
            ->where('EXPENDITURE_RECORD_ID', $expenditureRecordId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }
        if ($query->count() > 0) {
            throw new RuntimeException('expenditure record is already bound to a debit note', 400);
        }
    }

    private function playStatusFor(int $settlementAmountCents, int $amountCents): string
    {
        return $settlementAmountCents === $amountCents ? self::ALREADY_SETTLED : self::UNSETTLED;
    }

    private function activeSettlementAccount(string $id, array $payload, bool $lock = false): array
    {
        $query = Db::name('settlement_account')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if ($lock) {
            $query->lock(true);
        }

        $row = $query
            ->field('ID,CREATE_USER,TENANT_ID,org AS ORG')
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return $row;
    }

    private function assertSettlementAccountRowWritable(array $row, array $payload, string $action): array
    {
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $accountOrg = trim((string)($row['ORG'] ?? $row['org'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $accountOrg !== '' && in_array($accountOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} with this settlement account", 403);
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

    private function tenantId(array $input, array $payload): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
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

    /**
     * @return array<int, array{id: string, amount: string, amountCents: int}>
     */
    private function requiredBatchItems(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('missing items', 400);
        }

        $seen = [];
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid items', 400);
            }

            $id = $this->requiredInput($item, 'id');
            if (isset($seen[$id])) {
                throw new RuntimeException('duplicate debit note item', 400);
            }
            $seen[$id] = true;

            $amountCents = $this->positiveMoneyCents($item['amount'] ?? null);
            $normalized[] = [
                'id' => $id,
                'amount' => $this->moneyFromCents($amountCents),
                'amountCents' => $amountCents,
            ];
        }

        return $normalized;
    }

    private function requiredTime(array $input, string $key): string
    {
        $value = $this->requiredInput($input, $key);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$key}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function positiveMoneyCents(mixed $value): int
    {
        $cents = $this->moneyCents($value);
        if ($cents <= 0) {
            throw new RuntimeException('amount must be greater than 0', 400);
        }

        return $cents;
    }

    private function nonNegativeMoneyCents(mixed $value, string $key): int
    {
        $cents = $this->moneyCents($value);
        if ($cents < 0) {
            throw new RuntimeException("{$key} must be greater than or equal to 0", 400);
        }

        return $cents;
    }

    private function moneyCents(mixed $value): int
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('invalid amount', 400);
        }

        $normalized = trim((string)$value);
        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            if (!is_numeric($value)) {
                throw new RuntimeException('invalid amount', 400);
            }
            $normalized = number_format((float)$value, 2, '.', '');
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0');
        $cents = ((int)$whole * 100) + (int)str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function moneyFromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign . (string)intdiv($absolute, 100) . '.' . str_pad((string)($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
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
