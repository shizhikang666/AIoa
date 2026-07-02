<?php

declare(strict_types=1);

namespace app\service\user;

use app\service\auth\TokenService;
use RuntimeException;
use Throwable;
use think\facade\Db;

class UserOrgMigrationService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const LOG_TABLE = 'sys_user_org_migration_log';
    private const MAX_SKIPPED_SAMPLES = 100;

    /**
     * @var array<int, string>
     */
    private const BUSINESS_TABLE_NAMES = [
        'customer',
        'customer_follow_up',
        'delivery_record',
        'inventory',
        'product_relation',
        'return_order',
        'return_order_item',
        'sale_project_follow_up',
        'sale_project_product_item_relation',
        'sale_project_rate',
        'settlement_account',
        'settlement_account_statement',
        'supplier',
        'warehouses',
    ];

    public function __construct(
        private readonly TokenService $tokenService = new TokenService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function preview(array $input, array $payload): array
    {
        $this->assertAdmin($payload);
        $request = $this->migrationRequest($input, false);
        $context = $this->validateContext($request);

        return $this->publicPreview($this->buildPreview($request, $context));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function execute(array $input, array $payload): array
    {
        $this->assertAdmin($payload);
        $request = $this->migrationRequest($input, true);
        $context = $this->validateContext($request);
        $preview = $this->buildPreview($request, $context);

        if (!hash_equals((string)$request['previewHash'], (string)$preview['previewHash'])) {
            throw new RuntimeException('preview expired; run preview again', 409);
        }

        $this->ensureLogTable();

        $logId = $this->newId();
        $operatorId = $this->payloadUserId($payload);
        $executedAt = date('Y-m-d H:i:s');

        try {
            $executed = Db::transaction(function () use ($request, $context, $preview, $payload, $logId, $operatorId, $executedAt): array {
                $this->lockAndValidateUser($context);

                $updatedTables = [];
                $updatedBusinessRows = 0;
                foreach ($preview['tables'] as $tablePlan) {
                    $updatedRows = $this->executeTablePlan($tablePlan, (string)$context['sourceOrgId'], (string)$request['targetOrgId']);
                    if ($updatedRows > 0 || (int)($tablePlan['affectedRows'] ?? 0) > 0) {
                        $updatedTables[] = [
                            'table' => (string)$tablePlan['table'],
                            'affectedRows' => (int)($tablePlan['affectedRows'] ?? 0),
                            'updatedRows' => $updatedRows,
                        ];
                    }
                    $updatedBusinessRows += $updatedRows;
                }

                Db::name('sys_user')
                    ->where('ID', (string)$request['userId'])
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->update([
                        'ORG_ID' => (string)$request['targetOrgId'],
                        'POSITION_ID' => (string)$request['targetPositionId'],
                        'UPDATE_TIME' => $executedAt,
                        'UPDATE_USER' => $operatorId !== '' ? $operatorId : null,
                    ]);

                $this->insertLog(
                    $logId,
                    'SUCCESS',
                    $context,
                    $preview,
                    $payload,
                    [
                        'updatedBusinessRows' => $updatedBusinessRows,
                        'updatedTables' => $updatedTables,
                    ],
                    null,
                    0
                );

                return [
                    'logId' => $logId,
                    'updatedBusinessRows' => $updatedBusinessRows,
                    'updatedTables' => $updatedTables,
                ];
            });
        } catch (Throwable $exception) {
            $this->insertFailureLog($logId, $context, $preview, $payload, $exception->getMessage());
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('migration execute failed', 500);
        }

        $revokedTokens = $this->tokenService->revokeUserTokens((string)$request['userId']);
        Db::name(self::LOG_TABLE)
            ->where('ID', $logId)
            ->update(['REVOKED_TOKENS' => $revokedTokens]);

        return array_merge($this->publicPreview($preview), [
            'logId' => $logId,
            'executedAt' => $executedAt,
            'updatedBusinessRows' => (int)$executed['updatedBusinessRows'],
            'updatedTables' => $executed['updatedTables'],
            'revokedTokens' => $revokedTokens,
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function logPage(array $filters, array $payload): array
    {
        $this->assertAdmin($payload);
        [$page, $limit] = $this->pagination($filters);

        if (!$this->tableExists(self::LOG_TABLE)) {
            return $this->emptyPage($page, $limit);
        }

        $total = $this->logQuery($filters)->count();
        $rows = $this->logQuery($filters)
            ->order('CREATE_TIME', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => array_map(fn (array $row): array => $this->logRow($row, false), $rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function logDetail(string $id, array $payload): array
    {
        $this->assertAdmin($payload);
        if (!$this->tableExists(self::LOG_TABLE)) {
            throw new RuntimeException('migration log not found', 404);
        }

        $row = Db::name(self::LOG_TABLE)->where('ID', $id)->find();
        if (!$row) {
            throw new RuntimeException('migration log not found', 404);
        }

        return $this->logRow($row, true);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{userId:string,targetOrgId:string,targetPositionId:string,previewHash?:string}
     */
    private function migrationRequest(array $input, bool $requireHash): array
    {
        $request = [
            'userId' => $this->requiredInput($input, 'userId'),
            'targetOrgId' => $this->requiredInput($input, 'targetOrgId'),
            'targetPositionId' => $this->requiredInput($input, 'targetPositionId'),
        ];

        if ($requireHash) {
            $request['previewHash'] = $this->requiredInput($input, 'previewHash');
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function validateContext(array $request): array
    {
        $user = $this->activeRow('sys_user', (string)$request['userId'], 'user not found');
        $targetOrg = $this->activeRow('sys_org', (string)$request['targetOrgId'], 'target org not found');
        $targetPosition = $this->activeRow('sys_position', (string)$request['targetPositionId'], 'target position not found');

        $account = strtolower(trim((string)($user['ACCOUNT'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            throw new RuntimeException('built-in admin user cannot be migrated', 403);
        }

        if ((string)($targetPosition['ORG_ID'] ?? '') !== (string)$request['targetOrgId']) {
            throw new RuntimeException('target position does not belong to target org', 400);
        }

        $userTenantId = trim((string)($user['TENANT_ID'] ?? ''));
        $targetOrgTenantId = trim((string)($targetOrg['TENANT_ID'] ?? ''));
        if ($userTenantId !== $targetOrgTenantId) {
            throw new RuntimeException('cross-tenant user migration is not supported', 400);
        }

        $targetPositionTenantId = trim((string)($targetPosition['TENANT_ID'] ?? ''));
        if ($targetOrgTenantId !== '' && $targetPositionTenantId !== '' && $targetOrgTenantId !== $targetPositionTenantId) {
            throw new RuntimeException('target position tenant does not match target org', 400);
        }

        $sourceOrgId = trim((string)($user['ORG_ID'] ?? ''));
        $sourcePositionId = trim((string)($user['POSITION_ID'] ?? ''));

        return [
            'user' => $user,
            'targetOrg' => $targetOrg,
            'targetPosition' => $targetPosition,
            'userId' => (string)$request['userId'],
            'userAccount' => (string)($user['ACCOUNT'] ?? ''),
            'userName' => (string)($user['NAME'] ?? ''),
            'tenantId' => $userTenantId !== '' ? $userTenantId : $targetOrgTenantId,
            'sourceOrgId' => $sourceOrgId,
            'sourceOrgName' => $this->orgName($sourceOrgId),
            'sourcePositionId' => $sourcePositionId,
            'sourcePositionName' => $this->positionName($sourcePositionId),
            'targetOrgId' => (string)$request['targetOrgId'],
            'targetOrgName' => (string)($targetOrg['NAME'] ?? ''),
            'targetPositionId' => (string)$request['targetPositionId'],
            'targetPositionName' => (string)($targetPosition['NAME'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildPreview(array $request, array $context): array
    {
        $tables = [];
        $skipped = [];
        $summary = [
            'tableCount' => 0,
            'matchedRows' => 0,
            'affectedRows' => 0,
            'skippedRows' => 0,
            'unchangedRows' => 0,
        ];

        foreach ($this->businessOwnerTables() as $tableInfo) {
            $plan = $this->previewTable(
                $tableInfo,
                (string)$request['userId'],
                (string)$context['sourceOrgId'],
                (string)$request['targetOrgId'],
                $skipped
            );

            if ((int)$plan['matchedRows'] === 0) {
                continue;
            }

            $tables[] = $plan;
            $summary['matchedRows'] += (int)$plan['matchedRows'];
            $summary['affectedRows'] += (int)$plan['affectedRows'];
            $summary['skippedRows'] += (int)$plan['skippedRows'];
            $summary['unchangedRows'] += (int)$plan['unchangedRows'];
        }

        usort($tables, static fn (array $a, array $b): int => strcmp((string)$a['table'], (string)$b['table']));
        $summary['tableCount'] = count($tables);

        $preview = [
            'user' => [
                'id' => (string)$context['userId'],
                'account' => (string)$context['userAccount'],
                'name' => (string)$context['userName'],
                'sourceOrgId' => (string)$context['sourceOrgId'],
                'sourceOrgName' => (string)$context['sourceOrgName'],
                'sourcePositionId' => (string)$context['sourcePositionId'],
                'sourcePositionName' => (string)$context['sourcePositionName'],
                'tenantId' => (string)$context['tenantId'],
            ],
            'target' => [
                'orgId' => (string)$request['targetOrgId'],
                'orgName' => (string)$context['targetOrgName'],
                'positionId' => (string)$request['targetPositionId'],
                'positionName' => (string)$context['targetPositionName'],
            ],
            'summary' => $summary,
            'tables' => $tables,
            'skipped' => $skipped,
            'createdAt' => date('Y-m-d H:i:s'),
        ];

        $preview['previewHash'] = $this->previewHash($request, $context, $tables);

        return $preview;
    }

    /**
     * @param array<string, mixed> $tableInfo
     * @param array<int, array<string, mixed>> $skipped
     * @return array<string, mixed>
     */
    private function previewTable(array $tableInfo, string $userId, string $sourceOrgId, string $targetOrgId, array &$skipped): array
    {
        $table = (string)$tableInfo['table'];
        $ownerColumns = $tableInfo['ownerColumns'];
        $orgColumns = $tableInfo['orgColumns'];
        $idColumn = $tableInfo['idColumn'];
        $fields = array_values(array_unique(array_filter(array_merge(
            $idColumn !== null ? [$idColumn] : [],
            $ownerColumns,
            $orgColumns
        ))));

        $rows = $this->ownerRows($table, $fields, $ownerColumns, $userId, $idColumn);
        $plan = [
            'table' => $table,
            'ownerColumns' => $ownerColumns,
            'orgColumns' => $orgColumns,
            'idColumn' => $idColumn,
            'matchedRows' => count($rows),
            'affectedRows' => 0,
            'skippedRows' => 0,
            'unchangedRows' => 0,
            'reason' => 'no_matching_rows',
            'affectedIds' => [],
            'fingerprint' => '',
        ];

        if ($rows === []) {
            $plan['fingerprint'] = $this->fingerprint([]);
            return $plan;
        }

        $fingerprintRows = [];
        if ($orgColumns === []) {
            $plan['unchangedRows'] = count($rows);
            $plan['reason'] = 'no_org_column';
            foreach ($rows as $row) {
                $fingerprintRows[] = $this->fingerprintRow($row, $idColumn, $ownerColumns, $orgColumns);
            }
            $plan['fingerprint'] = $this->fingerprint($fingerprintRows);
            return $plan;
        }

        foreach ($rows as $row) {
            $fingerprintRows[] = $this->fingerprintRow($row, $idColumn, $ownerColumns, $orgColumns);
            $id = $idColumn === null ? '' : trim((string)$this->rowValue($row, $idColumn));
            if ($id === '') {
                $plan['skippedRows']++;
                $this->addSkipped($skipped, [
                    'table' => $table,
                    'id' => null,
                    'reason' => 'missing_primary_key',
                    'orgValues' => $this->columnValues($row, $orgColumns),
                ]);
                continue;
            }

            $decision = $this->orgDecision($row, $orgColumns, $sourceOrgId, $targetOrgId);
            if ($decision['action'] === 'skip') {
                $plan['skippedRows']++;
                $this->addSkipped($skipped, [
                    'table' => $table,
                    'id' => $id,
                    'reason' => 'third_party_org',
                    'orgValues' => $decision['orgValues'],
                ]);
                continue;
            }

            if ($decision['action'] === 'update') {
                $plan['affectedRows']++;
                $plan['affectedIds'][] = $id;
                continue;
            }

            $plan['unchangedRows']++;
        }

        $plan['reason'] = $this->tableReason($plan);
        $plan['fingerprint'] = $this->fingerprint($fingerprintRows);

        return $plan;
    }

    /**
     * @param array<string, mixed> $tablePlan
     */
    private function executeTablePlan(array $tablePlan, string $sourceOrgId, string $targetOrgId): int
    {
        $table = (string)$tablePlan['table'];
        $ids = array_values(array_filter(array_map('strval', $tablePlan['affectedIds'] ?? [])));
        $orgColumns = array_values(array_filter(array_map('strval', $tablePlan['orgColumns'] ?? [])));
        $idColumn = trim((string)($tablePlan['idColumn'] ?? 'ID'));
        if ($ids === [] || $orgColumns === []) {
            return 0;
        }

        $updated = 0;
        $fields = array_values(array_unique(array_merge([$idColumn], $orgColumns)));
        $updates = [];
        foreach ($orgColumns as $column) {
            $updates[$column] = $targetOrgId;
        }

        foreach ($ids as $id) {
            $row = Db::table($table)
                ->field($fields)
                ->where($idColumn, $id)
                ->lock(true)
                ->find();
            if (!$row) {
                throw new RuntimeException('migration data changed; run preview again', 409);
            }

            $decision = $this->orgDecision($row, $orgColumns, $sourceOrgId, $targetOrgId);
            if ($decision['action'] !== 'update') {
                throw new RuntimeException('migration data changed; run preview again', 409);
            }

            Db::table($table)->where($idColumn, $id)->update($updates);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function lockAndValidateUser(array $context): void
    {
        $user = Db::name('sys_user')
            ->where('ID', (string)$context['userId'])
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->lock(true)
            ->find();

        if (!$user) {
            throw new RuntimeException('user not found', 404);
        }

        if (
            trim((string)($user['ORG_ID'] ?? '')) !== (string)$context['sourceOrgId']
            || trim((string)($user['POSITION_ID'] ?? '')) !== (string)$context['sourcePositionId']
        ) {
            throw new RuntimeException('migration user changed; run preview again', 409);
        }
    }

    /**
     * @return array<int, array{table:string,ownerColumns:array<int,string>,orgColumns:array<int,string>,idColumn:?string}>
     */
    private function businessOwnerTables(): array
    {
        $tables = [];
        foreach ($this->baseTables() as $table) {
            if (!$this->isBusinessTable($table)) {
                continue;
            }

            $columns = $this->tableColumns($table);
            $ownerColumns = $this->matchingColumns($columns, ['user', 'user_id']);
            if ($ownerColumns === []) {
                continue;
            }

            $tables[] = [
                'table' => $table,
                'ownerColumns' => $ownerColumns,
                'orgColumns' => $this->matchingColumns($columns, ['org', 'org_id']),
                'idColumn' => $columns['id'] ?? null,
            ];
        }

        usort($tables, static fn (array $a, array $b): int => strcmp($a['table'], $b['table']));

        return $tables;
    }

    /**
     * @return array<int, string>
     */
    private function baseTables(): array
    {
        $rows = Db::query('SHOW FULL TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $values = array_values($row);
            $table = trim((string)($values[0] ?? ''));
            $type = strtoupper(trim((string)($values[1] ?? 'BASE TABLE')));
            if ($table !== '' && $type === 'BASE TABLE') {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    /**
     * @return array<string, string>
     */
    private function tableColumns(string $table): array
    {
        $rows = Db::query('SHOW COLUMNS FROM ' . $this->quoteIdentifier($table));
        $columns = [];
        foreach ($rows as $row) {
            $field = trim((string)($row['Field'] ?? ''));
            if ($field !== '') {
                $columns[strtolower($field)] = $field;
            }
        }

        return $columns;
    }

    /**
     * @param array<string, string> $columns
     * @param array<int, string> $needles
     * @return array<int, string>
     */
    private function matchingColumns(array $columns, array $needles): array
    {
        $matches = [];
        foreach ($needles as $needle) {
            if (isset($columns[$needle])) {
                $matches[] = $columns[$needle];
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @param array<int, string> $fields
     * @param array<int, string> $ownerColumns
     * @return array<int, array<string, mixed>>
     */
    private function ownerRows(string $table, array $fields, array $ownerColumns, string $userId, ?string $idColumn): array
    {
        $query = Db::table($table)->field($fields);
        $query->where(function ($query) use ($ownerColumns, $userId): void {
            $first = true;
            foreach ($ownerColumns as $column) {
                if ($first) {
                    $query->where($column, $userId);
                    $first = false;
                    continue;
                }
                $query->whereOr($column, $userId);
            }
        });

        if ($idColumn !== null && $idColumn !== '') {
            $query->order($idColumn, 'asc');
        }

        return $query->select()->toArray();
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $orgColumns
     * @return array{action:string,orgValues:array<string, string>}
     */
    private function orgDecision(array $row, array $orgColumns, string $sourceOrgId, string $targetOrgId): array
    {
        $hasUpdatableOrg = false;
        $hasThirdPartyOrg = false;
        $orgValues = [];

        foreach ($orgColumns as $column) {
            $value = trim((string)$this->rowValue($row, $column));
            $orgValues[$column] = $value;

            if ($targetOrgId !== '' && $value === $targetOrgId) {
                continue;
            }
            if ($value === '') {
                $hasUpdatableOrg = true;
                continue;
            }
            if ($sourceOrgId !== '' && $value === $sourceOrgId) {
                $hasUpdatableOrg = true;
                continue;
            }

            $hasThirdPartyOrg = true;
        }

        if ($hasThirdPartyOrg) {
            return ['action' => 'skip', 'orgValues' => $orgValues];
        }

        return [
            'action' => $hasUpdatableOrg ? 'update' : 'unchanged',
            'orgValues' => $orgValues,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     * @return array<string, string>
     */
    private function columnValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = trim((string)$this->rowValue($row, $column));
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $ownerColumns
     * @param array<int, string> $orgColumns
     * @return array<string, mixed>
     */
    private function fingerprintRow(array $row, ?string $idColumn, array $ownerColumns, array $orgColumns): array
    {
        return [
            'id' => $idColumn === null ? '' : trim((string)$this->rowValue($row, $idColumn)),
            'owner' => $this->columnValues($row, $ownerColumns),
            'org' => $this->columnValues($row, $orgColumns),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function fingerprint(array $rows): string
    {
        usort($rows, static fn (array $a, array $b): int => strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? '')));

        return hash('sha256', $this->json($rows));
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $tables
     */
    private function previewHash(array $request, array $context, array $tables): string
    {
        $hashTables = [];
        foreach ($tables as $table) {
            $hashTables[] = [
                'table' => (string)$table['table'],
                'ownerColumns' => $table['ownerColumns'],
                'orgColumns' => $table['orgColumns'],
                'idColumn' => $table['idColumn'] ?? null,
                'matchedRows' => (int)$table['matchedRows'],
                'affectedRows' => (int)$table['affectedRows'],
                'skippedRows' => (int)$table['skippedRows'],
                'unchangedRows' => (int)$table['unchangedRows'],
                'affectedIds' => $table['affectedIds'],
                'fingerprint' => (string)$table['fingerprint'],
            ];
        }

        return hash('sha256', $this->json([
            'userId' => (string)$request['userId'],
            'sourceOrgId' => (string)$context['sourceOrgId'],
            'sourcePositionId' => (string)$context['sourcePositionId'],
            'targetOrgId' => (string)$request['targetOrgId'],
            'targetPositionId' => (string)$request['targetPositionId'],
            'tenantId' => (string)$context['tenantId'],
            'tables' => $hashTables,
        ]));
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function publicPreview(array $preview): array
    {
        $public = $preview;
        foreach ($public['tables'] as &$table) {
            unset($table['affectedIds'], $table['fingerprint']);
        }
        unset($table);

        return $public;
    }

    /**
     * @param array<int, array<string, mixed>> $skipped
     * @param array<string, mixed> $row
     */
    private function addSkipped(array &$skipped, array $row): void
    {
        if (count($skipped) >= self::MAX_SKIPPED_SAMPLES) {
            return;
        }

        $skipped[] = $row;
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function tableReason(array $plan): string
    {
        if ((int)$plan['affectedRows'] > 0 && (int)$plan['skippedRows'] > 0) {
            return 'partial_update_with_anomaly';
        }
        if ((int)$plan['affectedRows'] > 0) {
            return 'will_update';
        }
        if ((int)$plan['skippedRows'] > 0) {
            return 'skipped_anomaly';
        }

        return 'unchanged';
    }

    private function isBusinessTable(string $table): bool
    {
        $name = strtolower($table);
        if ($name === self::LOG_TABLE) {
            return false;
        }

        foreach (['sys_', 'act_', 'dev_', 'gen_', 'auth_', 'client_', 'mobile_'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return false;
            }
        }

        if (in_array($name, ['tenants'], true)) {
            return false;
        }

        return str_starts_with($name, 'biz_') || in_array($name, self::BUSINESS_TABLE_NAMES, true);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $execution
     */
    private function insertLog(
        string $logId,
        string $status,
        array $context,
        array $preview,
        array $payload,
        array $execution,
        ?string $errorMessage,
        int $revokedTokens
    ): void {
        Db::name(self::LOG_TABLE)->insert([
            'ID' => $logId,
            'USER_ID' => (string)$context['userId'],
            'USER_ACCOUNT' => (string)$context['userAccount'],
            'USER_NAME' => (string)$context['userName'],
            'SOURCE_ORG_ID' => (string)$context['sourceOrgId'],
            'SOURCE_ORG_NAME' => (string)$context['sourceOrgName'],
            'TARGET_ORG_ID' => (string)$context['targetOrgId'],
            'TARGET_ORG_NAME' => (string)$context['targetOrgName'],
            'SOURCE_POSITION_ID' => (string)$context['sourcePositionId'],
            'SOURCE_POSITION_NAME' => (string)$context['sourcePositionName'],
            'TARGET_POSITION_ID' => (string)$context['targetPositionId'],
            'TARGET_POSITION_NAME' => (string)$context['targetPositionName'],
            'TENANT_ID' => (string)$context['tenantId'],
            'PREVIEW_HASH' => (string)$preview['previewHash'],
            'STATUS' => $status,
            'AFFECTED_JSON' => $this->json([
                'summary' => $preview['summary'] ?? [],
                'tables' => array_map(fn (array $table): array => [
                    'table' => (string)$table['table'],
                    'ownerColumns' => $table['ownerColumns'],
                    'orgColumns' => $table['orgColumns'],
                    'idColumn' => $table['idColumn'] ?? null,
                    'matchedRows' => (int)$table['matchedRows'],
                    'affectedRows' => (int)$table['affectedRows'],
                    'skippedRows' => (int)$table['skippedRows'],
                    'unchangedRows' => (int)$table['unchangedRows'],
                    'reason' => (string)$table['reason'],
                ], $preview['tables'] ?? []),
                'execution' => $execution,
            ]),
            'SKIPPED_JSON' => $this->json($preview['skipped'] ?? []),
            'ERROR_MESSAGE' => $errorMessage,
            'REVOKED_TOKENS' => $revokedTokens,
            'CREATE_TIME' => date('Y-m-d H:i:s'),
            'CREATE_USER' => $this->payloadUserId($payload) !== '' ? $this->payloadUserId($payload) : null,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $payload
     */
    private function insertFailureLog(string $logId, array $context, array $preview, array $payload, string $message): void
    {
        try {
            $this->insertLog($logId, 'FAILED', $context, $preview, $payload, [], substr($message, 0, 1000), 0);
        } catch (Throwable) {
            // The original execute error is more important than a best-effort failure log.
        }
    }

    private function ensureLogTable(): void
    {
        Db::execute(
            'CREATE TABLE IF NOT EXISTS `sys_user_org_migration_log` (' .
            '`ID` varchar(32) NOT NULL,' .
            '`USER_ID` varchar(32) NOT NULL,' .
            '`USER_ACCOUNT` varchar(100) DEFAULT NULL,' .
            '`USER_NAME` varchar(100) DEFAULT NULL,' .
            '`SOURCE_ORG_ID` varchar(32) DEFAULT NULL,' .
            '`SOURCE_ORG_NAME` varchar(200) DEFAULT NULL,' .
            '`TARGET_ORG_ID` varchar(32) DEFAULT NULL,' .
            '`TARGET_ORG_NAME` varchar(200) DEFAULT NULL,' .
            '`SOURCE_POSITION_ID` varchar(32) DEFAULT NULL,' .
            '`SOURCE_POSITION_NAME` varchar(200) DEFAULT NULL,' .
            '`TARGET_POSITION_ID` varchar(32) DEFAULT NULL,' .
            '`TARGET_POSITION_NAME` varchar(200) DEFAULT NULL,' .
            '`TENANT_ID` varchar(32) DEFAULT NULL,' .
            '`PREVIEW_HASH` varchar(64) NOT NULL,' .
            '`STATUS` varchar(20) NOT NULL,' .
            '`AFFECTED_JSON` longtext NULL,' .
            '`SKIPPED_JSON` longtext NULL,' .
            '`ERROR_MESSAGE` varchar(1000) DEFAULT NULL,' .
            '`REVOKED_TOKENS` int NOT NULL DEFAULT 0,' .
            '`CREATE_TIME` datetime DEFAULT NULL,' .
            '`CREATE_USER` varchar(32) DEFAULT NULL,' .
            'PRIMARY KEY (`ID`),' .
            'KEY `idx_user_org_migration_user` (`USER_ID`),' .
            'KEY `idx_user_org_migration_time` (`CREATE_TIME`)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function tableExists(string $table): bool
    {
        $safe = $this->escapeSqlString($table);
        return Db::query("SHOW TABLES LIKE '{$safe}'") !== [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function logQuery(array $filters): mixed
    {
        $query = Db::name(self::LOG_TABLE);
        $userId = trim((string)($filters['userId'] ?? ''));
        if ($userId !== '') {
            $query->where('USER_ID', $userId);
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $query->where('STATUS', strtoupper($status));
        }

        $tenantId = trim((string)($filters['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $searchKey = trim((string)($filters['searchKey'] ?? ''));
        if ($searchKey !== '') {
            $query->where(function ($query) use ($searchKey): void {
                $query->whereLike('USER_ACCOUNT', '%' . $searchKey . '%')
                    ->whereOr('USER_NAME', 'like', '%' . $searchKey . '%');
            });
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function logRow(array $row, bool $detail): array
    {
        $data = [
            'id' => (string)($row['ID'] ?? ''),
            'userId' => (string)($row['USER_ID'] ?? ''),
            'userAccount' => (string)($row['USER_ACCOUNT'] ?? ''),
            'userName' => (string)($row['USER_NAME'] ?? ''),
            'sourceOrgId' => (string)($row['SOURCE_ORG_ID'] ?? ''),
            'sourceOrgName' => (string)($row['SOURCE_ORG_NAME'] ?? ''),
            'targetOrgId' => (string)($row['TARGET_ORG_ID'] ?? ''),
            'targetOrgName' => (string)($row['TARGET_ORG_NAME'] ?? ''),
            'sourcePositionId' => (string)($row['SOURCE_POSITION_ID'] ?? ''),
            'sourcePositionName' => (string)($row['SOURCE_POSITION_NAME'] ?? ''),
            'targetPositionId' => (string)($row['TARGET_POSITION_ID'] ?? ''),
            'targetPositionName' => (string)($row['TARGET_POSITION_NAME'] ?? ''),
            'tenantId' => (string)($row['TENANT_ID'] ?? ''),
            'previewHash' => (string)($row['PREVIEW_HASH'] ?? ''),
            'status' => (string)($row['STATUS'] ?? ''),
            'errorMessage' => (string)($row['ERROR_MESSAGE'] ?? ''),
            'revokedTokens' => (int)($row['REVOKED_TOKENS'] ?? 0),
            'createTime' => (string)($row['CREATE_TIME'] ?? ''),
            'createUser' => (string)($row['CREATE_USER'] ?? ''),
        ];

        if ($detail) {
            $data['affected'] = $this->jsonDecode((string)($row['AFFECTED_JSON'] ?? ''));
            $data['skipped'] = $this->jsonDecode((string)($row['SKIPPED_JSON'] ?? ''));
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:int,1:int}
     */
    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['size'] ?? $filters['limit'] ?? 10)));

        return [$page, $limit];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPage(int $page, int $limit): array
    {
        return [
            'records' => [],
            'total' => 0,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRow(string $table, string $id, string $message): array
    {
        $row = Db::name($table)
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->find();

        if (!$row) {
            throw new RuntimeException($message, 404);
        }

        return $row;
    }

    private function orgName(string $id): string
    {
        if ($id === '') {
            return '';
        }

        $row = Db::name('sys_org')->where('ID', $id)->field(['NAME'])->find();
        return $row ? (string)($row['NAME'] ?? '') : '';
    }

    private function positionName(string $id): string
    {
        if ($id === '') {
            return '';
        }

        $row = Db::name('sys_position')->where('ID', $id)->field(['NAME'])->find();
        return $row ? (string)($row['NAME'] ?? '') : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertAdmin(array $payload): void
    {
        if (!$this->isAdmin($payload)) {
            throw new RuntimeException('permission denied', 403);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isAdmin(array $payload): bool
    {
        $account = strtolower(trim((string)($payload['account'] ?? '')));
        if (in_array($account, ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
            return true;
        }

        foreach ($this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []) as $roleCode) {
            if (in_array(strtolower($roleCode), ['superadmin', 'bizadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
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

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['code'] ?? $item['roleCode'] ?? $item['value'] ?? '';
            }
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowValue(array $row, string $column): mixed
    {
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }

        $lower = strtolower($column);
        foreach ($row as $key => $value) {
            if (strtolower((string)$key) === $lower) {
                return $value;
            }
        }

        return null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('invalid database identifier', 500);
        }

        return '`' . $identifier . '`';
    }

    private function escapeSqlString(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function json(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : 'null';
    }

    private function jsonDecode(string $json): mixed
    {
        $json = trim($json);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }
}
