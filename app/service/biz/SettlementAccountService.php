<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Settlement-account queries and base writes compatible with Java SettlementAccountController.
 */
class SettlementAccountService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const ENABLE = 'ENABLE';
    private const DISABLED = 'DISABLED';
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

    public function add(array $input, array $payload = []): array
    {
        $accountName = $this->requiredInput($input, 'accountName');
        $accountNumber = $this->requiredInput($input, 'accountNumber');
        $status = $this->accountStatus($input['accountStatus'] ?? self::ENABLE);

        return Db::transaction(function () use ($input, $payload, $accountName, $accountNumber, $status): array {
            $tenantId = $this->tenantId($input, $payload);
            $this->assertUniqueAccountName($accountName, $tenantId);

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $id = $this->newId();
            $initialAmount = $this->decimalAmount($input['initialAmount'] ?? 0);
            $row = [
                'ID' => $id,
                'ACCOUNT_NAME' => $accountName,
                'ACCOUNT_NUMBER' => $accountNumber,
                'INITIAL_AMOUNT' => $initialAmount,
                'CURRENT_AMOUNT' => $initialAmount,
                'ACCOUNT_STATUS' => $status,
                'SORT_CODE' => $this->nullableInt($input['sortCode'] ?? null),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
            ];

            $orgId = $this->nullableString($input['org'] ?? $input['orgId'] ?? null) ?? $this->defaultOrgId($payload);
            if ($orgId !== null) {
                $row['org'] = $orgId;
            }
            $this->assertNewAccountWritable($row, $payload);

            Db::name('settlement_account')->insert($row);

            return ['id' => $id];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $accountName = $this->requiredInput($input, 'accountName');

        return Db::transaction(function () use ($id, $input, $payload, $accountName): array {
            $account = $this->assertAccountWritable($id, $payload, 'edit');
            $tenantId = (string)($account['TENANT_ID'] ?? $this->tenantId($input, $payload));
            if ($accountName !== (string)($account['ACCOUNT_NAME'] ?? '')) {
                $this->assertUniqueAccountName($accountName, $tenantId, $id);
            }

            $row = [
                'ACCOUNT_NAME' => $accountName,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => ($this->currentUserId($payload) !== '') ? $this->currentUserId($payload) : null,
            ];
            if (array_key_exists('accountStatus', $input)) {
                $row['ACCOUNT_STATUS'] = $this->accountStatus($input['accountStatus']);
            }
            if (array_key_exists('sortCode', $input)) {
                $row['SORT_CODE'] = $this->nullableInt($input['sortCode']);
            }
            if (array_key_exists('org', $input) || array_key_exists('orgId', $input)) {
                $orgId = $this->nullableString($input['org'] ?? $input['orgId'] ?? null);
                $this->assertOrgWritable($orgId, $payload);
                $row['org'] = $orgId;
            }

            $updated = Db::name('settlement_account')
                ->where('ID', $id)
                ->update($row);

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function editStatus(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');
        $status = $this->accountStatus($input['accountStatus'] ?? null);

        return Db::transaction(function () use ($id, $status, $payload): array {
            $this->assertAccountWritable($id, $payload, 'edit status');
            $userId = $this->currentUserId($payload);
            $updated = Db::name('settlement_account')
                ->where('ID', $id)
                ->update([
                    'ACCOUNT_STATUS' => $status,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['id' => $id, 'count' => $updated];
        });
    }

    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing id', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $accounts = $this->lockedAccounts($idList, $payload);
            foreach ($idList as $id) {
                $this->assertAccountRowWritable($accounts[$id], $payload, 'delete');
            }

            $this->assertAccountsUnreferenced($idList);

            $userId = $this->currentUserId($payload);
            $updated = Db::name('settlement_account')
                ->whereIn('ID', $idList)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return ['ids' => $idList, 'count' => $updated];
        });
    }

    public function expensesAdd(array $input, array $payload = []): array
    {
        return $this->createExpenses($input, $payload, 'Process_sys', 'Process_sys');
    }

    public function expensesFromWorkflow(
        array $input,
        string $processInstanceId,
        string $tenantId,
        string $operatorUserId,
        string $processCategory
    ): array {
        $workflowInput = $input;
        if (!isset($workflowInput['targetId']) || trim((string)$workflowInput['targetId']) === '') {
            $workflowInput['targetId'] = $workflowInput['accountId'] ?? '';
        }

        return $this->createExpenses(
            $workflowInput,
            [
                'tenant_id' => $tenantId,
                'user_id' => $operatorUserId,
            ],
            $processInstanceId,
            $processCategory,
            $operatorUserId,
            true
        );
    }

    private function createExpenses(
        array $input,
        array $payload,
        string $processId,
        string $processCategory,
        ?string $operatorUserId = null,
        bool $skipPermissionCheck = false
    ): array {
        $targetId = $this->requiredInput($input, 'targetId');
        $settlementCategory = $this->requiredCategory($input['settlementCategory'] ?? null);
        $payer = $this->requiredInput($input, 'payer');
        $payerTime = $this->requiredTime($input, 'payerTime');
        $amountCents = $this->positiveMoneyCents($input['amount'] ?? null);
        $amount = $this->moneyFromCents($amountCents);
        $objectId = $this->nullableString($input['objectId'] ?? null);
        $bankName = $this->nullableString($input['bankName'] ?? null);
        $bankAccount = $this->nullableString($input['bankAccount'] ?? null);
        $remark = $this->nullableString($input['remark'] ?? null);

        return Db::transaction(function () use ($input, $payload, $targetId, $settlementCategory, $payer, $payerTime, $amountCents, $amount, $objectId, $bankName, $bankAccount, $remark, $processId, $processCategory, $operatorUserId, $skipPermissionCheck): array {
            $account = $this->lockedAccount($targetId, $payload);
            if (!$skipPermissionCheck) {
                $account = $this->assertAccountRowWritable($account, $payload, 'add expenses');
            }
            $beforeAmount = $this->moneyFromCents($this->moneyCents($account['CURRENT_AMOUNT'] ?? '0'));
            $afterAmount = $this->moneyFromCents($this->moneyCents($beforeAmount) - $amountCents);
            $tenantId = trim((string)($account['TENANT_ID'] ?? ''));
            if ($tenantId === '') {
                $tenantId = $this->tenantId($input, $payload);
            }
            $userId = trim((string)($operatorUserId ?? $this->currentUserId($payload)));
            $orgId = trim((string)($account['org'] ?? $account['ORG'] ?? ''));
            $now = date('Y-m-d H:i:s');
            $statementId = $this->newId();
            $expenditureId = $this->newId();

            $audit = [
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $tenantId,
            ];

            Db::name('settlement_account_statement')->insert(array_merge($audit, [
                'ID' => $statementId,
                'ACCOUNT_ID' => $targetId,
                'PROCESS_ID' => $processId,
                'AFTER_AMOUNT' => $afterAmount,
                'BEFORE_AMOUNT' => $beforeAmount,
                'AMOUNT' => $amount,
                'SETTLEMENT_TYPE' => 'EXPEND',
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PROCESS_CATEGORY' => $processCategory,
                'PAYER_TIME' => $payerTime,
                'DELETE_FLAG' => self::NOT_DELETE,
            ]));

            Db::name('biz_expenditure_record')->insert(array_merge($audit, [
                'ID' => $expenditureId,
                'OBJECT_ID' => $objectId,
                'TARGET_ID' => $targetId,
                'SERIAL_ID' => $statementId,
                'PROCESS_ID' => $processId,
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PAYER' => $payer,
                'BANK_NAME' => $bankName,
                'BANK_ACCOUNT' => $bankAccount,
                'REMARK' => $remark,
                'PAYER_TIME' => $payerTime,
                'AMOUNT' => $amount,
                'DELETE_FLAG' => self::NOT_DELETE,
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $orgId !== '' ? $orgId : null,
            ]));

            $accountUpdated = Db::name('settlement_account')
                ->where('ID', $targetId)
                ->update([
                    'CURRENT_AMOUNT' => $afterAmount,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return [
                'id' => $expenditureId,
                'statementId' => $statementId,
                'accountId' => $targetId,
                'amount' => $amount,
                'beforeAmount' => $beforeAmount,
                'afterAmount' => $afterAmount,
                'accountCount' => $accountUpdated,
            ];
        });
    }

    public function paymentAdd(array $input, array $payload = []): array
    {
        return $this->createPayment($input, $payload, 'Process_sys', 'Process_sys');
    }

    public function paymentFromWorkflow(
        array $input,
        string $processInstanceId,
        string $tenantId,
        string $operatorUserId,
        string $processCategory = 'Process_payment'
    ): array {
        $workflowInput = $input;
        if (!isset($workflowInput['targetId']) || trim((string)$workflowInput['targetId']) === '') {
            $workflowInput['targetId'] = $workflowInput['accountId'] ?? '';
        }
        if (!isset($workflowInput['payer']) || trim((string)$workflowInput['payer']) === '') {
            $workflowInput['payer'] = $workflowInput['treasurer'] ?? $operatorUserId;
        }

        return $this->createPayment(
            $workflowInput,
            [
                'tenant_id' => $tenantId,
                'user_id' => $operatorUserId,
            ],
            $processInstanceId,
            $processCategory,
            $operatorUserId,
            true
        );
    }

    private function createPayment(
        array $input,
        array $payload,
        string $processId,
        string $processCategory,
        ?string $operatorUserId = null,
        bool $skipPermissionCheck = false
    ): array {
        $targetId = $this->requiredInput($input, 'targetId');
        $settlementCategory = $this->requiredCategory($input['settlementCategory'] ?? null);
        $payer = $this->requiredInput($input, 'payer');
        $payerTime = $this->requiredTime($input, 'payerTime');
        $amountCents = $this->positiveMoneyCents($input['amount'] ?? null);
        $amount = $this->moneyFromCents($amountCents);
        $objectId = $this->nullableString($input['objectId'] ?? null);
        $bankName = $this->nullableString($input['bankName'] ?? null);
        $bankAccount = $this->nullableString($input['bankAccount'] ?? null);
        $remark = $this->nullableString($input['remark'] ?? null);

        return Db::transaction(function () use ($input, $payload, $targetId, $settlementCategory, $payer, $payerTime, $amountCents, $amount, $objectId, $bankName, $bankAccount, $remark, $processId, $processCategory, $operatorUserId, $skipPermissionCheck): array {
            $account = $this->lockedAccount($targetId, $payload);
            if (!$skipPermissionCheck) {
                $account = $this->assertAccountRowWritable($account, $payload, 'add payment');
            }
            $beforeAmount = $this->moneyFromCents($this->moneyCents($account['CURRENT_AMOUNT'] ?? '0'));
            $afterAmount = $this->moneyFromCents($this->moneyCents($beforeAmount) + $amountCents);
            $tenantId = trim((string)($account['TENANT_ID'] ?? ''));
            if ($tenantId === '') {
                $tenantId = $this->tenantId($input, $payload);
            }
            $userId = trim((string)($operatorUserId ?? $this->currentUserId($payload)));
            $orgId = trim((string)($account['org'] ?? $account['ORG'] ?? ''));
            $now = date('Y-m-d H:i:s');
            $statementId = $this->newId();
            $paymentId = $this->newId();

            $audit = [
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $tenantId,
            ];

            Db::name('settlement_account_statement')->insert(array_merge($audit, [
                'ID' => $statementId,
                'ACCOUNT_ID' => $targetId,
                'PROCESS_ID' => $processId,
                'AFTER_AMOUNT' => $afterAmount,
                'BEFORE_AMOUNT' => $beforeAmount,
                'AMOUNT' => $amount,
                'SETTLEMENT_TYPE' => 'INCOME',
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PROCESS_CATEGORY' => $processCategory,
                'PAYER_TIME' => $payerTime,
                'DELETE_FLAG' => self::NOT_DELETE,
            ]));

            Db::name('biz_payment_record')->insert(array_merge($audit, [
                'ID' => $paymentId,
                'OBJECT_ID' => $objectId,
                'TARGET_ID' => $targetId,
                'SERIAL_ID' => $statementId,
                'PROCESS_ID' => $processId,
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PAYER' => $payer,
                'BANK_NAME' => $bankName,
                'BANK_ACCOUNT' => $bankAccount,
                'REMARK' => $remark,
                'PAYER_TIME' => $payerTime,
                'AMOUNT' => $amount,
                'DELETE_FLAG' => self::NOT_DELETE,
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $orgId !== '' ? $orgId : null,
            ]));

            $accountUpdated = Db::name('settlement_account')
                ->where('ID', $targetId)
                ->update([
                    'CURRENT_AMOUNT' => $afterAmount,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return [
                'id' => $paymentId,
                'statementId' => $statementId,
                'accountId' => $targetId,
                'amount' => $amount,
                'beforeAmount' => $beforeAmount,
                'afterAmount' => $afterAmount,
                'accountCount' => $accountUpdated,
            ];
        });
    }

    public function transferAdd(array $input, array $payload = []): array
    {
        $expensesAccountId = $this->requiredInput($input, 'expensesAccountId');
        $revenueAccountId = $this->requiredInput($input, 'revenueAccountId');
        if ($expensesAccountId === $revenueAccountId) {
            throw new RuntimeException('transfer accounts must be different', 400);
        }

        $payerTime = $this->requiredTime($input, 'payerTime');
        $amountCents = $this->positiveMoneyCents($input['amount'] ?? null);
        $amount = $this->moneyFromCents($amountCents);
        $remark = $this->nullableString($input['remark'] ?? null);
        $settlementCategory = 'dealings';

        return Db::transaction(function () use ($input, $payload, $expensesAccountId, $revenueAccountId, $payerTime, $amountCents, $amount, $remark, $settlementCategory): array {
            $accounts = $this->lockedAccounts([$expensesAccountId, $revenueAccountId], $payload);
            $expensesAccount = $this->assertAccountRowWritable($accounts[$expensesAccountId], $payload, 'transfer from this settlement account');
            $revenueAccount = $this->assertAccountRowWritable($accounts[$revenueAccountId], $payload, 'transfer to this settlement account');

            $expensesBefore = $this->moneyFromCents($this->moneyCents($expensesAccount['CURRENT_AMOUNT'] ?? '0'));
            $expensesAfter = $this->moneyFromCents($this->moneyCents($expensesBefore) - $amountCents);
            $revenueBefore = $this->moneyFromCents($this->moneyCents($revenueAccount['CURRENT_AMOUNT'] ?? '0'));
            $revenueAfter = $this->moneyFromCents($this->moneyCents($revenueBefore) + $amountCents);

            $userId = $this->currentUserId($payload);
            $now = date('Y-m-d H:i:s');
            $processId = 'Process_sys';
            $expensesStatementId = $this->newId();
            $revenueStatementId = $this->newId();
            $expenditureId = $this->newId();
            $paymentId = $this->newId();

            $expensesTenantId = trim((string)($expensesAccount['TENANT_ID'] ?? ''));
            if ($expensesTenantId === '') {
                $expensesTenantId = $this->tenantId($input, $payload);
            }
            $revenueTenantId = trim((string)($revenueAccount['TENANT_ID'] ?? ''));
            if ($revenueTenantId === '') {
                $revenueTenantId = $this->tenantId($input, $payload);
            }

            $expensesOrgId = trim((string)($expensesAccount['org'] ?? $expensesAccount['ORG'] ?? ''));
            $revenueOrgId = trim((string)($revenueAccount['org'] ?? $revenueAccount['ORG'] ?? ''));
            $expensesAccountName = trim((string)($expensesAccount['ACCOUNT_NAME'] ?? ''));
            $expensesAccountNumber = trim((string)($expensesAccount['ACCOUNT_NUMBER'] ?? ''));
            $revenueAccountName = trim((string)($revenueAccount['ACCOUNT_NAME'] ?? ''));
            $revenueAccountNumber = trim((string)($revenueAccount['ACCOUNT_NUMBER'] ?? ''));

            $expensesAudit = [
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $expensesTenantId,
            ];
            $revenueAudit = [
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'TENANT_ID' => $revenueTenantId,
            ];

            Db::name('settlement_account_statement')->insert(array_merge($expensesAudit, [
                'ID' => $expensesStatementId,
                'ACCOUNT_ID' => $expensesAccountId,
                'PROCESS_ID' => $processId,
                'AFTER_AMOUNT' => $expensesAfter,
                'BEFORE_AMOUNT' => $expensesBefore,
                'AMOUNT' => $amount,
                'SETTLEMENT_TYPE' => 'EXPEND',
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PROCESS_CATEGORY' => $processId,
                'PAYER_TIME' => $payerTime,
                'DELETE_FLAG' => self::NOT_DELETE,
            ]));
            Db::name('settlement_account_statement')->insert(array_merge($revenueAudit, [
                'ID' => $revenueStatementId,
                'ACCOUNT_ID' => $revenueAccountId,
                'PROCESS_ID' => $processId,
                'AFTER_AMOUNT' => $revenueAfter,
                'BEFORE_AMOUNT' => $revenueBefore,
                'AMOUNT' => $amount,
                'SETTLEMENT_TYPE' => 'INCOME',
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PROCESS_CATEGORY' => $processId,
                'PAYER_TIME' => $payerTime,
                'DELETE_FLAG' => self::NOT_DELETE,
            ]));

            Db::name('biz_expenditure_record')->insert(array_merge($expensesAudit, [
                'ID' => $expenditureId,
                'OBJECT_ID' => $revenueAccountId,
                'TARGET_ID' => $expensesAccountId,
                'SERIAL_ID' => $expensesStatementId,
                'PROCESS_ID' => $processId,
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PAYER' => $revenueAccountName !== '' ? $revenueAccountName : null,
                'BANK_NAME' => null,
                'BANK_ACCOUNT' => $revenueAccountNumber !== '' ? $revenueAccountNumber : null,
                'REMARK' => $remark,
                'PAYER_TIME' => $payerTime,
                'AMOUNT' => $amount,
                'DELETE_FLAG' => self::NOT_DELETE,
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $expensesOrgId !== '' ? $expensesOrgId : null,
            ]));
            Db::name('biz_payment_record')->insert(array_merge($revenueAudit, [
                'ID' => $paymentId,
                'OBJECT_ID' => $expensesAccountId,
                'TARGET_ID' => $revenueAccountId,
                'SERIAL_ID' => $revenueStatementId,
                'PROCESS_ID' => $processId,
                'SETTLEMENT_CATEGORY' => $settlementCategory,
                'PAYER' => $expensesAccountName !== '' ? $expensesAccountName : null,
                'BANK_NAME' => null,
                'BANK_ACCOUNT' => $expensesAccountNumber !== '' ? $expensesAccountNumber : null,
                'REMARK' => $remark,
                'PAYER_TIME' => $payerTime,
                'AMOUNT' => $amount,
                'DELETE_FLAG' => self::NOT_DELETE,
                'USER' => $userId !== '' ? $userId : null,
                'ORG' => $revenueOrgId !== '' ? $revenueOrgId : null,
            ]));

            $expensesAccountUpdated = Db::name('settlement_account')
                ->where('ID', $expensesAccountId)
                ->update([
                    'CURRENT_AMOUNT' => $expensesAfter,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);
            $revenueAccountUpdated = Db::name('settlement_account')
                ->where('ID', $revenueAccountId)
                ->update([
                    'CURRENT_AMOUNT' => $revenueAfter,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                ]);

            return [
                'amount' => $amount,
                'settlementCategory' => $settlementCategory,
                'expenses' => [
                    'id' => $expenditureId,
                    'statementId' => $expensesStatementId,
                    'accountId' => $expensesAccountId,
                    'objectId' => $revenueAccountId,
                    'beforeAmount' => $expensesBefore,
                    'afterAmount' => $expensesAfter,
                    'accountCount' => $expensesAccountUpdated,
                ],
                'income' => [
                    'id' => $paymentId,
                    'statementId' => $revenueStatementId,
                    'accountId' => $revenueAccountId,
                    'objectId' => $expensesAccountId,
                    'beforeAmount' => $revenueBefore,
                    'afterAmount' => $revenueAfter,
                    'accountCount' => $revenueAccountUpdated,
                ],
            ];
        });
    }

    private function assertAccountWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeAccount($id, $payload);
        return $this->assertAccountRowWritable($row, $payload, $action);
    }

    private function assertAccountRowWritable(array $row, array $payload, string $action): array
    {
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $accountOrg = trim((string)($row['org'] ?? $row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $accountOrg !== '' && in_array($accountOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action} this settlement account", 403);
    }

    private function assertNewAccountWritable(array $row, array $payload): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $accountOrg = trim((string)($row['org'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $accountOrg !== '' && in_array($accountOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException('no permission to add this settlement account', 403);
    }

    private function assertOrgWritable(?string $orgId, array $payload): void
    {
        if ($orgId === null || $orgId === '' || $this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && in_array($orgId, $scopeOrgIds, true)) {
            return;
        }

        throw new RuntimeException('no permission to use this organization', 403);
    }

    private function activeAccount(string $id, array $payload): array
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

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return $row;
    }

    private function lockedAccount(string $id, array $payload): array
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

        $row = $query->lock(true)->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('settlement account not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function lockedAccounts(array $ids, array $payload): array
    {
        $ids = array_values(array_unique(array_map(
            static fn (string $id): string => trim($id),
            $ids
        )));
        sort($ids, SORT_STRING);

        $query = Db::name('settlement_account')
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query->order('ID', 'asc')->lock(true)->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[(string)($row['ID'] ?? '')] = $row;
        }

        foreach ($ids as $id) {
            if (!isset($map[$id])) {
                throw new RuntimeException('settlement account not found', 404);
            }
        }

        return $map;
    }

    private function assertUniqueAccountName(string $accountName, string $tenantId, ?string $ignoreId = null): void
    {
        $query = Db::name('settlement_account')
            ->where('ACCOUNT_NAME', $accountName)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('ID', '<>', $ignoreId);
        }

        if ($query->count() > 0) {
            throw new RuntimeException('settlement account name already exists', 400);
        }
    }

    /**
     * @param array<int, string> $ids
     */
    private function assertAccountsUnreferenced(array $ids): void
    {
        $checks = [
            ['settlement_account_statement', 'ACCOUNT_ID'],
            ['biz_payment_record', 'TARGET_ID'],
            ['biz_payment_record', 'OBJECT_ID'],
            ['biz_expenditure_record', 'TARGET_ID'],
            ['biz_expenditure_record', 'OBJECT_ID'],
        ];

        foreach ($checks as [$table, $column]) {
            if ($this->activeReferenceCount($table, $column, $ids) > 0) {
                throw new RuntimeException('settlement account is referenced', 400);
            }
        }
    }

    /**
     * @param array<int, string> $ids
     */
    private function activeReferenceCount(string $table, string $column, array $ids): int
    {
        return (int)Db::name($table)
            ->whereIn($column, $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->count();
    }

    private function accountStatus(mixed $value): string
    {
        $status = trim((string)$value);
        if (!in_array($status, [self::ENABLE, self::DISABLED], true)) {
            throw new RuntimeException('unsupported settlement account status', 400);
        }

        return $status;
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

    private function defaultOrgId(array $payload): ?string
    {
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? ''));
        if ($orgId !== '') {
            return $orgId;
        }

        $userId = $this->currentUserId($payload);
        if ($userId === '') {
            return null;
        }

        $user = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ORG_ID')
            ->find();
        if (!is_array($user) || $user === []) {
            return null;
        }

        $orgId = trim((string)($user['ORG_ID'] ?? ''));

        return $orgId !== '' ? $orgId : null;
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

    private function requiredCategory(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode('/', array_values(array_filter(array_map(
                static fn (mixed $part): string => trim((string)$part),
                $value
            ))));
        } else {
            $value = trim((string)$value);
        }

        if ($value === '') {
            throw new RuntimeException('missing settlementCategory', 400);
        }

        return $value;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function stringList(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $value = $value['id'] ?? $value['ID'] ?? '';
            }
            $id = trim((string)$value);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
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

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimalAmount(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid amount', 400);
        }

        return number_format((float)$value, 2, '.', '');
    }

    private function positiveMoneyCents(mixed $value): int
    {
        $cents = $this->moneyCents($value);
        if ($cents <= 0) {
            throw new RuntimeException('amount must be greater than 0', 400);
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
