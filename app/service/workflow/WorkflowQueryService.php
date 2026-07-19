<?php

declare(strict_types=1);

namespace app\service\workflow;

use app\model\ActHiActinst;
use app\model\ActHiComment;
use app\model\ActHiProcinst;
use app\model\ActHiTaskinst;
use app\model\ActHiVarinst;
use app\model\ActReProcdef;
use app\model\ActRuExecution;
use app\model\ActRuTask;
use app\model\ActRuVariable;
use app\support\WorkflowTitleFormatter;
use app\support\TenantScope;
use RuntimeException;
use think\facade\Db;

/**
 * Read-only workflow query service for Camunda-compatible process/task data.
 */
class WorkflowQueryService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $userCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $orgNameCache = [];

    /** @var array<string, array<int, string>> */
    private array $tenantUserIdCache = [];

    public function __construct(private readonly WorkflowVariableService $variableService = new WorkflowVariableService())
    {
    }

    public function pendingTaskCount(string $userId): int
    {
        return ActRuTask::where('ASSIGNEE_', $userId)->count();
    }

    public function pendingTaskPage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->pendingTaskQuery($userId, $filters)->count();
        $records = $this->taskRows(
            $this->pendingTaskQuery($userId, $filters)
                ->order(['CREATE_TIME_' => 'desc', 'ID_' => 'desc'])
                ->page($page, $limit)
                ->select()
                ->toArray(),
            true
        );

        return [
            'records' => $records,
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
    public function pendingTaskList(string $userId, array $filters = []): array
    {
        return $this->taskRows(
            $this->pendingTaskQuery($userId, $filters)
                ->order(['CREATE_TIME_' => 'desc', 'ID_' => 'desc'])
                ->select()
                ->toArray(),
            true
        );
    }

    public function historyTaskPage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->historyProcessQueryForUser($userId, $filters)->count();
        $records = $this->historicProcessRows(
            $this->historyProcessQueryForUser($userId, $filters)
                ->order($this->historyProcessSort($filters))
                ->page($page, $limit)
                ->select()
                ->toArray()
        );

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function startedProcessPage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->startedProcessQuery($userId, $filters)->count();
        $records = $this->historicProcessRows(
            $this->startedProcessQuery($userId, $filters)
                ->order(['START_TIME_' => 'desc', 'ID_' => 'desc'])
                ->page($page, $limit)
                ->select()
                ->toArray()
        );

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function processDetail(string $processInstanceId, string $currentUserId = ''): array
    {
        $process = ActHiProcinst::where('PROC_INST_ID_', $processInstanceId)->find();
        $activities = ActHiActinst::where('PROC_INST_ID_', $processInstanceId)
            ->order(['START_TIME_' => 'asc', 'ID_' => 'asc'])
            ->select()
            ->toArray();
        $comments = ActHiComment::where('PROC_INST_ID_', $processInstanceId)
            ->order(['TIME_' => 'asc', 'ID_' => 'asc'])
            ->select()
            ->toArray();
        $activityVariables = $this->activityVariableMap($processInstanceId);
        $processRow = $process ? $process->toArray() : ['ID_' => $processInstanceId, 'PROC_INST_ID_' => $processInstanceId];
        $variables = $this->variableService->historyByProcessInstance($processInstanceId);
        $userProcess = $this->processRow($processRow, $variables);
        $currentTask = $this->currentRuntimeTask($processInstanceId, $currentUserId);

        return [
            'process' => $process ? $processRow : null,
            'variables' => $variables,
            'activities' => $activities,
            'comments' => $comments,
            'userProcess' => $userProcess,
            'startUser' => $this->userById((string)($userProcess['startUserId'] ?? $variables['initiator'] ?? '')),
            'startOrgTree' => $this->orgTree((string)($variables['org'] ?? '')),
            'userActivityList' => $this->userActivityList($activities, $comments, $activityVariables),
            'ccUser' => $this->usersByIds($this->arrayValue($variables['ccUserIdList'] ?? $variables['copyUserIdList'] ?? [])),
            'currentTask' => $currentTask,
            'currentTaskId' => $currentTask['taskId'] ?? null,
        ];
    }

    public function assertProcessReadable(string $processInstanceId, array $payload = []): void
    {
        $processInstanceId = trim($processInstanceId);
        $currentUserId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? ''));
        $currentTenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($processInstanceId === '' || $currentUserId === '' || $currentTenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        $process = ActHiProcinst::where('PROC_INST_ID_', $processInstanceId)->find();
        if (!$process) {
            throw new RuntimeException('permission denied', 403);
        }

        $processRow = $process->toArray();
        $processTenantId = $this->historicalProcessTenantId($processRow);
        if ($processTenantId === '' || !hash_equals($currentTenantId, $processTenantId)) {
            throw new RuntimeException('permission denied', 403);
        }

        if ((string)($processRow['START_USER_ID_'] ?? '') === $currentUserId) {
            return;
        }

        if (ActRuTask::where('PROC_INST_ID_', $processInstanceId)->where('ASSIGNEE_', $currentUserId)->count() > 0) {
            return;
        }
        if (ActHiTaskinst::where('PROC_INST_ID_', $processInstanceId)->where('ASSIGNEE_', $currentUserId)->count() > 0) {
            return;
        }
        if ($this->isProcessCopyUser($processInstanceId, $currentUserId, $currentTenantId)) {
            return;
        }
        if ($this->hasBuiltInProcessReadRole($payload)) {
            return;
        }
        if ($this->hasScopedAllProcessRead($processRow, $payload)) {
            return;
        }

        throw new RuntimeException('permission denied', 403);
    }

    public function allProcessPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->authorizedAllProcessQuery($filters, $payload)->count();
        $records = $this->processSummaryRows($this->historicProcessRows(
            $this->authorizedAllProcessQuery($filters, $payload)
                ->order(['START_TIME_' => 'desc', 'ID_' => 'desc'])
                ->page($page, $limit)
                ->select()
                ->toArray()
        ), true);

        return [
            'records' => $records,
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
    public function queryProcess(array $filters = [], array $payload = []): array
    {
        $variableName = trim((string)($filters['variableName'] ?? ''));
        if ($variableName === '') {
            throw new RuntimeException('missing variableName', 400);
        }

        $values = $this->arrayValue($filters['variable'] ?? $filters['variableList'] ?? []);
        $findValue = $this->arrayValue($filters['findValue'] ?? []);
        $processCategory = trim((string)($filters['processCategory'] ?? $filters['category'] ?? ''));
        $normalizedVariableName = strtolower($variableName);
        if (!in_array($normalizedVariableName, ['projectid', 'bizsaleprojectid', 'objectid'], true)) {
            throw new RuntimeException('invalid variableName', 400);
        }
        $allowedFindValues = in_array($normalizedVariableName, ['projectid', 'bizsaleprojectid'], true)
            ? ['amount']
            : [];
        $requestedFindValues = array_map(
            static fn (mixed $value): string => strtolower(trim((string)$value)),
            $findValue
        );
        if (array_diff($requestedFindValues, $allowedFindValues) !== []) {
            throw new RuntimeException('invalid findValue', 400);
        }

        return array_map(function (mixed $value) use ($variableName, $findValue, $processCategory, $payload): array {
            $processIds = $this->runtimeProcessIdsByVariable($variableName, $value, $processCategory);
            $processIds = $this->runtimeProcessIdsForTenant($processIds, $payload);

            return [
                'variable' => $value,
                'processIdList' => $processIds,
                'variableMap' => $findValue === [] ? [] : $this->runtimeVariableMapForNames($processIds, $findValue),
            ];
        }, $values);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function queryProcessList(array $filters = [], array $payload = []): array
    {
        $processKeys = $this->arrayValue($filters['processKeyList'] ?? $filters['processKeys'] ?? $filters['category'] ?? []);
        $attributes = $filters['attribute'] ?? [];
        $attributes = is_array($attributes) ? $attributes : [];
        $attributes = array_filter(
            $attributes,
            fn (mixed $value, mixed $name): bool => trim((string)$name) !== '' && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH
        );

        if ($processKeys === []) {
            throw new RuntimeException('missing processKeyList', 400);
        }
        if ($attributes === []) {
            throw new RuntimeException('missing attribute', 400);
        }

        $processIds = null;
        foreach ($attributes as $name => $value) {
            $matched = $this->historyProcessIdsByVariable((string)$name, $value);
            $processIds = $processIds === null ? $matched : array_values(array_intersect($processIds, $matched));
        }

        $processIds = $this->historyProcessIdsForTenant($processIds ?? [], $payload);
        if ($processIds === []) {
            return [];
        }

        $query = ActHiProcinst::where([]);
        if ($processKeys !== []) {
            $query->whereIn('PROC_DEF_KEY_', array_map('strval', $processKeys));
        }
        $query->whereIn('PROC_INST_ID_', $processIds);

        $rows = $this->historicProcessRows(
            $query->order(['START_TIME_' => 'desc', 'ID_' => 'desc'])
                ->select()
                ->toArray()
        );

        return $this->processSummaryRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectRuntimeQueryList(array $filters = [], array $payload = []): array
    {
        $projectId = trim((string)($filters['projectId'] ?? ''));
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        $processIds = $this->runtimeProcessIdsByVariable('projectId', $projectId);
        $processIds = $this->runtimeProcessIdsForTenant($processIds, $payload);

        return $this->processSummaryRows($this->runtimeProcessRows($processIds));
    }

    /**
     * @param array<int, string> $processIds
     * @return array<int, string>
     */
    private function runtimeProcessIdsForTenant(array $processIds, array $payload): array
    {
        if ($processIds === []) {
            return [];
        }

        $tenantId = $this->requiredPayloadTenantId($payload);
        if ($tenantId === null) {
            return array_values(array_unique(array_map('strval', $processIds)));
        }

        $explicitTenantRows = Db::name('act_ru_execution')
            ->whereIn('PROC_INST_ID_', $processIds)
            ->whereNotNull('TENANT_ID_')
            ->where('TENANT_ID_', '<>', '')
            ->field('PROC_INST_ID_,TENANT_ID_')
            ->select()
            ->toArray();
        $explicitTenantsByProcess = [];
        foreach ($explicitTenantRows as $row) {
            $processId = trim((string)($row['PROC_INST_ID_'] ?? ''));
            $explicitTenantId = trim((string)($row['TENANT_ID_'] ?? ''));
            if ($processId !== '' && $explicitTenantId !== '') {
                $explicitTenantsByProcess[$processId][$explicitTenantId] = true;
            }
        }

        $runtimeIds = [];
        $legacyProcessIds = [];
        foreach ($processIds as $processId) {
            $explicitTenantIds = array_keys($explicitTenantsByProcess[$processId] ?? []);
            if ($explicitTenantIds === []) {
                $legacyProcessIds[] = $processId;
            } elseif ($explicitTenantIds === [$tenantId]) {
                $runtimeIds[] = $processId;
            }
        }
        $historicIds = $this->historyProcessIdsForTenant($legacyProcessIds, $payload);

        return array_values(array_unique(array_map(
            'strval',
            array_merge($runtimeIds, $historicIds)
        )));
    }

    /**
     * Migrated Java workflow rows may have an empty TENANT_ID_. Prefer their
     * persisted tenantId variable and use the starter's current tenant only
     * when that historical variable never existed.
     *
     * @param array<int, string> $processIds
     * @return array<int, string>
     */
    private function historyProcessIdsForTenant(array $processIds, array $payload): array
    {
        $processIds = array_values(array_unique(array_filter(array_map('strval', $processIds))));
        if ($processIds === []) {
            return [];
        }

        $tenantId = $this->requiredPayloadTenantId($payload);
        if ($tenantId === null) {
            return $processIds;
        }

        $query = ActHiProcinst::whereIn('PROC_INST_ID_', $processIds);
        $this->applyHistoricalTenantScope($query, $tenantId);

        return array_values(array_unique(array_map('strval', $query->column('PROC_INST_ID_'))));
    }

    /**
     * Empty payloads are retained for isolated service tests. Every HTTP call
     * supplies an auth payload and must either be platform-superadmin or carry
     * a concrete tenant.
     */
    private function requiredPayloadTenantId(array $payload): ?string
    {
        if ($payload === [] || TenantScope::canCrossTenant($payload)) {
            return null;
        }

        $tenantId = TenantScope::tenantId($payload);
        if ($tenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        return $tenantId;
    }

    public function runtimeActivityDetail(string $taskId, string $userId): array
    {
        $task = ActRuTask::where('ID_', $taskId)
            ->where('ASSIGNEE_', $userId)
            ->find();
        if (!$task) {
            throw new RuntimeException('task not found or completed', 404);
        }

        $taskRow = $task->toArray();
        $processInstanceId = (string)($taskRow['PROC_INST_ID_'] ?? '');

        return [
            'category' => $taskRow['TASK_DEF_KEY_'] ?? null,
            'variables' => $this->variableService->runtimeByProcessInstance($processInstanceId),
            'taskId' => $taskRow['ID_'] ?? $taskId,
            'processKey' => $this->definitionKey((string)($taskRow['PROC_DEF_ID_'] ?? '')),
            'processInstanceId' => $processInstanceId,
            'processDefinitionId' => $taskRow['PROC_DEF_ID_'] ?? null,
        ];
    }

    private function pendingTaskQuery(string $userId, array $filters)
    {
        $query = ActRuTask::where('ASSIGNEE_', $userId);

        if (!empty($filters['processInstanceId'])) {
            $query->where('PROC_INST_ID_', (string)$filters['processInstanceId']);
        }

        if (!empty($filters['processDefinitionId'])) {
            $query->where('PROC_DEF_ID_', (string)$filters['processDefinitionId']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID_', (string)$filters['tenantId']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentRuntimeTask(string $processInstanceId, string $userId): ?array
    {
        $processInstanceId = trim($processInstanceId);
        $userId = trim($userId);
        if ($processInstanceId === '' || $userId === '') {
            return null;
        }

        $task = ActRuTask::where('PROC_INST_ID_', $processInstanceId)
            ->where('ASSIGNEE_', $userId)
            ->order(['CREATE_TIME_' => 'asc', 'ID_' => 'asc'])
            ->find();
        if (!$task) {
            return null;
        }

        $taskRow = $task->toArray();
        $taskId = (string)($taskRow['ID_'] ?? '');
        if ($taskId === '') {
            return null;
        }

        return [
            'id' => $taskId,
            'taskId' => $taskId,
            'name' => $taskRow['NAME_'] ?? null,
            'category' => $taskRow['TASK_DEF_KEY_'] ?? null,
            'taskDefinitionKey' => $taskRow['TASK_DEF_KEY_'] ?? null,
            'processKey' => $this->definitionKey((string)($taskRow['PROC_DEF_ID_'] ?? '')),
            'processInstanceId' => $taskRow['PROC_INST_ID_'] ?? $processInstanceId,
            'processDefinitionId' => $taskRow['PROC_DEF_ID_'] ?? null,
            'createTime' => $taskRow['CREATE_TIME_'] ?? null,
        ];
    }

    private function authorizedAllProcessQuery(array $filters, array $payload)
    {
        $query = $this->historicProcessQuery($filters, $payload);
        $scope = $this->allProcessReadScope($payload);
        if ($scope['allowAll']) {
            return $query;
        }

        if ($scope['orgIds'] !== []) {
            $this->applyHistoricalProcessOrgScope($query, $scope['orgIds']);

            return $query;
        }

        $this->applyHistoricalProcessParticipantScope(
            $query,
            $scope['userId'],
            $scope['tenantId']
        );

        return $query;
    }

    /**
     * @return array{allowAll: bool, orgIds: array<int, string>, userId: string, tenantId: string}
     */
    private function allProcessReadScope(array $payload): array
    {
        $userId = trim((string)($payload['user_id'] ?? $payload['userId'] ?? ''));
        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if (TenantScope::canCrossTenant($payload)) {
            return ['allowAll' => true, 'orgIds' => [], 'userId' => $userId, 'tenantId' => $tenantId];
        }
        if ($userId === '' || $tenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }
        if ($this->hasBuiltInProcessReadRole($payload)) {
            return ['allowAll' => true, 'orgIds' => [], 'userId' => $userId, 'tenantId' => $tenantId];
        }

        $orgIds = [];
        $hasExactScope = false;
        $dataScopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($dataScopes)) {
            foreach ($dataScopes as $scope) {
                if (!is_array($scope)) {
                    continue;
                }
                $apiUrl = strtolower(trim((string)($scope['apiUrl'] ?? $scope['api_url'] ?? '')));
                if ($apiUrl !== '/biz/process/all/page') {
                    continue;
                }
                $hasExactScope = true;
                $scopeCategory = strtoupper(trim((string)(
                    $scope['scopeCategory'] ?? $scope['scope_category'] ?? ''
                )));
                if ($scopeCategory === 'SCOPE_ALL') {
                    return ['allowAll' => true, 'orgIds' => [], 'userId' => $userId, 'tenantId' => $tenantId];
                }
                $orgIds = array_merge(
                    $orgIds,
                    $this->stringList($scope['scopeOrgIdList'] ?? $scope['scope_org_id_list'] ?? [])
                );
            }
        }

        // Missing/stale scope metadata fails closed to the user's own process
        // participation instead of exposing the tenant-wide process registry.
        return [
            'allowAll' => false,
            'orgIds' => $hasExactScope ? array_values(array_unique($orgIds)) : [],
            'userId' => $userId,
            'tenantId' => $tenantId,
        ];
    }

    /** @param array<int, string> $orgIds */
    private function applyHistoricalProcessOrgScope($query, array $orgIds): void
    {
        $orgIds = array_values(array_unique(array_filter(array_map('strval', $orgIds))));
        if ($orgIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $query->where(function ($scope) use ($orgIds, $placeholders): void {
            $scope->whereRaw(
                "EXISTS (SELECT 1 FROM act_hi_varinst org_var"
                . " WHERE BINARY org_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                . " AND org_var.NAME_ = 'org'"
                . " AND (org_var.TEXT_ IN ({$placeholders}) OR org_var.TEXT2_ IN ({$placeholders})"
                . " OR CAST(org_var.LONG_ AS CHAR) IN ({$placeholders})))",
                array_merge($orgIds, $orgIds, $orgIds)
            )->whereOr(function ($legacyOrgId) use ($orgIds, $placeholders): void {
                $legacyOrgId->whereRaw(
                    "NOT EXISTS (SELECT 1 FROM act_hi_varinst preferred_org_var"
                    . " WHERE BINARY preferred_org_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                    . " AND preferred_org_var.NAME_ = 'org')"
                )->whereRaw(
                    "EXISTS (SELECT 1 FROM act_hi_varinst org_id_var"
                    . " WHERE BINARY org_id_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                    . " AND org_id_var.NAME_ = 'orgId'"
                    . " AND (org_id_var.TEXT_ IN ({$placeholders}) OR org_id_var.TEXT2_ IN ({$placeholders})"
                    . " OR CAST(org_id_var.LONG_ AS CHAR) IN ({$placeholders})))",
                    array_merge($orgIds, $orgIds, $orgIds)
                );
            })->whereOr(function ($starterFallback) use ($orgIds): void {
                $starterFallback->whereRaw(
                    "NOT EXISTS (SELECT 1 FROM act_hi_varinst any_org_var"
                    . " WHERE BINARY any_org_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                    . " AND any_org_var.NAME_ IN ('org', 'orgId'))"
                )->whereIn('START_USER_ID_', Db::name('sys_user')->whereIn('ORG_ID', $orgIds)->column('ID'));
            });
        });
    }

    private function applyHistoricalProcessParticipantScope($query, string $userId, string $tenantId): void
    {
        if ($userId === '' || $tenantId === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($scope) use ($userId, $tenantId): void {
            $scope->where('START_USER_ID_', $userId)
                ->whereOrRaw(
                    'EXISTS (SELECT 1 FROM act_ru_task participant_ru_task'
                    . ' WHERE BINARY participant_ru_task.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_'
                    . ' AND participant_ru_task.ASSIGNEE_ = ?)',
                    [$userId]
                )->whereOrRaw(
                    'EXISTS (SELECT 1 FROM act_hi_taskinst participant_hi_task'
                    . ' WHERE BINARY participant_hi_task.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_'
                    . ' AND participant_hi_task.ASSIGNEE_ = ?)',
                    [$userId]
                )->whereOrRaw(
                    'EXISTS (SELECT 1 FROM biz_cc_records participant_cc'
                    . ' WHERE (BINARY participant_cc.PROCESS_ID = BINARY act_hi_procinst.PROC_INST_ID_'
                    . ' OR BINARY participant_cc.INSTANCE_ID = BINARY act_hi_procinst.PROC_INST_ID_)'
                    . ' AND participant_cc.USER = ? AND participant_cc.TENANT_ID = ?'
                    . " AND (participant_cc.DELETE_FLAG IS NULL OR participant_cc.DELETE_FLAG = 'NOT_DELETE'))",
                    [$userId, $tenantId]
                );
        });
    }

    private function isProcessCopyUser(string $processInstanceId, string $userId, string $tenantId): bool
    {
        return Db::name('biz_cc_records')
            ->where(function ($query) use ($processInstanceId): void {
                $query->where('PROCESS_ID', $processInstanceId)
                    ->whereOr('INSTANCE_ID', $processInstanceId);
            })
            ->where('USER', $userId)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->count() > 0;
    }

    private function hasBuiltInProcessReadRole(array $payload): bool
    {
        foreach ($this->stringList($payload['role_codes'] ?? $payload['roleCodeList'] ?? []) as $roleCode) {
            if (in_array($roleCode, ['superadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasScopedAllProcessRead(array $processRow, array $payload): bool
    {
        if (!in_array(
            '/biz/process/all/page',
            $this->stringList($payload['permission_codes'] ?? $payload['permissionCodeList'] ?? []),
            true
        )) {
            return false;
        }

        $processInstanceId = trim((string)($processRow['PROC_INST_ID_'] ?? $processRow['ID_'] ?? ''));
        $variables = $processInstanceId === '' ? [] : $this->variableService->historyByProcessInstance($processInstanceId);
        $processOrgId = trim((string)($variables['org'] ?? $variables['orgId'] ?? ''));
        if ($processOrgId === '') {
            $processOrgId = trim((string)Db::name('sys_user')
                ->where('ID', (string)($processRow['START_USER_ID_'] ?? ''))
                ->value('ORG_ID'));
        }

        $dataScopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (!is_array($dataScopes)) {
            return false;
        }

        foreach ($dataScopes as $scope) {
            if (!is_array($scope)) {
                continue;
            }
            $apiUrl = strtolower(trim((string)($scope['apiUrl'] ?? $scope['api_url'] ?? '')));
            if ($apiUrl !== '/biz/process/all/page') {
                continue;
            }
            if (strtoupper(trim((string)($scope['scopeCategory'] ?? $scope['scope_category'] ?? ''))) === 'SCOPE_ALL') {
                return true;
            }
            if ($processOrgId !== '' && in_array(
                $processOrgId,
                $this->stringList($scope['scopeOrgIdList'] ?? $scope['scope_org_id_list'] ?? []),
                true
            )) {
                return true;
            }
        }

        return false;
    }

    private function historyTaskQuery(string $userId, array $filters)
    {
        $query = ActHiTaskinst::where('ASSIGNEE_', $userId)
            ->whereNotNull('END_TIME_');

        if (!empty($filters['processInstanceId'])) {
            $query->where('PROC_INST_ID_', (string)$filters['processInstanceId']);
        }

        $processKey = trim((string)($filters['processKey'] ?? $filters['category'] ?? ''));
        if ($processKey !== '') {
            $query->where('PROC_DEF_KEY_', $processKey);
        }

        return $query;
    }

    private function historyProcessQueryForUser(string $userId, array $filters)
    {
        $processIds = $this->historyProcessIdsForUser($userId);
        $query = ActHiProcinst::where([]);
        if ($processIds === []) {
            $query->whereRaw('1=0');
            return $query;
        }

        $query->whereIn('PROC_INST_ID_', $processIds);

        if (!empty($filters['processInstanceId'])) {
            $query->where('PROC_INST_ID_', (string)$filters['processInstanceId']);
        }

        $processKey = trim((string)($filters['processKey'] ?? $filters['category'] ?? $filters['processCategory'] ?? ''));
        if ($processKey !== '') {
            $query->where('PROC_DEF_KEY_', $processKey);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID_', (string)$filters['tenantId']);
        }

        if (!empty($filters['state'])) {
            $query->where('STATE_', (string)$filters['state']);
        }

        $this->applyTimeRange($query, $filters, 'START_TIME_', 'startCreateTime', 'endCreateTime');
        $this->applyHistoryVariableLike($query, 'title', $filters['title'] ?? null);
        $this->applyHistoryVariableLike($query, 'amount', $filters['amount'] ?? null);

        return $query;
    }

    /**
     * @return array<int, string>
     */
    private function historyProcessIdsForUser(string $userId): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            return [];
        }

        $processIds = [];
        foreach ([
            ActHiTaskinst::where('ASSIGNEE_', $userId)->column('PROC_INST_ID_'),
            ActRuTask::where('ASSIGNEE_', $userId)->column('PROC_INST_ID_'),
            ActHiProcinst::where('START_USER_ID_', $userId)->column('PROC_INST_ID_'),
        ] as $ids) {
            foreach ($ids as $id) {
                $id = trim((string)$id);
                if ($id !== '') {
                    $processIds[] = $id;
                }
            }
        }

        return array_values(array_unique($processIds));
    }

    /**
     * @return array<string, string>
     */
    private function historyProcessSort(array $filters): array
    {
        $field = trim((string)($filters['sortField'] ?? ''));
        $order = strtolower(trim((string)($filters['sortOrder'] ?? 'descend'))) === 'ascend' ? 'asc' : 'desc';
        $column = match ($field) {
            'endTime', 'finishTime', 'completeTime' => 'END_TIME_',
            'createTime', 'startTime' => 'START_TIME_',
            default => 'START_TIME_',
        };

        return [$column => $order, 'ID_' => $order];
    }

    private function startedProcessQuery(string $userId, array $filters)
    {
        $query = ActHiProcinst::where('START_USER_ID_', $userId);

        if (!empty($filters['processKey'])) {
            $query->where('PROC_DEF_KEY_', (string)$filters['processKey']);
        }

        if (!empty($filters['state'])) {
            $query->where('STATE_', (string)$filters['state']);
        }

        if (!empty($filters['tenantId'])) {
            $query->where('TENANT_ID_', (string)$filters['tenantId']);
        }

        return $query;
    }

    private function historicProcessQuery(array $filters, array $payload = [])
    {
        $filters = TenantScope::scopedFilters($filters, $payload);
        $query = ActHiProcinst::where([]);

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $this->applyHistoricalTenantScope($query, $tenantId);
        }

        $processKey = trim((string)($filters['category'] ?? $filters['processKey'] ?? $filters['processCategory'] ?? ''));
        if ($processKey !== '') {
            $query->where('PROC_DEF_KEY_', $processKey);
        }

        if (!empty($filters['state'])) {
            $query->where('STATE_', (string)$filters['state']);
        }

        $this->applyTimeRange($query, $filters, 'START_TIME_', 'startCreateTime', 'endCreateTime');
        $this->applyHistoryVariableLike($query, 'title', $filters['title'] ?? null);
        $this->applyHistoryVariableLike($query, 'amount', $filters['amount'] ?? null);

        return $query;
    }

    private function applyHistoricalTenantScope($query, string $tenantId): void
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            throw new RuntimeException('permission denied', 403);
        }

        $tenantUserIds = $this->tenantUserIds($tenantId);
        $query->where(function ($scope) use ($tenantId, $tenantUserIds): void {
            $scope->where('TENANT_ID_', $tenantId);
            $scope->whereOr(function ($legacy) use ($tenantId, $tenantUserIds): void {
                $legacy->where(function ($emptyTenant): void {
                    $emptyTenant->whereNull('TENANT_ID_')->whereOr('TENANT_ID_', '');
                })->where(function ($identity) use ($tenantId, $tenantUserIds): void {
                    $identity->whereRaw(
                        "EXISTS (SELECT 1 FROM act_hi_varinst tenant_var"
                        . " WHERE BINARY tenant_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                        . " AND tenant_var.NAME_ = 'tenantId'"
                        . " AND (tenant_var.TEXT_ = ? OR tenant_var.TEXT2_ = ? OR CAST(tenant_var.LONG_ AS CHAR) = ?))",
                        [$tenantId, $tenantId, $tenantId]
                    );
                    if ($tenantUserIds !== []) {
                        $identity->whereOr(function ($starterFallback) use ($tenantUserIds): void {
                            $starterFallback->whereRaw(
                                "NOT EXISTS (SELECT 1 FROM act_hi_varinst any_tenant_var"
                                . " WHERE BINARY any_tenant_var.PROC_INST_ID_ = BINARY act_hi_procinst.PROC_INST_ID_"
                                . " AND any_tenant_var.NAME_ = 'tenantId')"
                            )->whereIn('START_USER_ID_', $tenantUserIds);
                        });
                    }
                });
            });
        });
    }

    /**
     * Resolve one historical process with the same precedence used by the
     * list query. A migrated tenant variable is authoritative when the
     * Activiti tenant column is blank; the starter's current tenant is only a
     * compatibility fallback for records that never stored tenantId at all.
     *
     * @param array<string, mixed> $processRow
     */
    private function historicalProcessTenantId(array $processRow): string
    {
        $explicitTenantId = trim((string)($processRow['TENANT_ID_'] ?? ''));
        if ($explicitTenantId !== '') {
            return $explicitTenantId;
        }

        $processInstanceId = trim((string)($processRow['PROC_INST_ID_'] ?? $processRow['ID_'] ?? ''));
        if ($processInstanceId === '') {
            return '';
        }

        $tenantVariable = Db::name('act_hi_varinst')
            ->where('PROC_INST_ID_', $processInstanceId)
            ->where('NAME_', 'tenantId')
            ->field('TEXT_,TEXT2_,LONG_')
            ->order('CREATE_TIME_', 'desc')
            ->find();
        if (is_array($tenantVariable) && $tenantVariable !== []) {
            foreach (['TEXT_', 'TEXT2_', 'LONG_'] as $column) {
                $tenantId = trim((string)($tenantVariable[$column] ?? ''));
                if ($tenantId !== '') {
                    return $tenantId;
                }
            }

            return '';
        }

        return trim((string)Db::name('sys_user')
            ->where('ID', (string)($processRow['START_USER_ID_'] ?? ''))
            ->value('TENANT_ID'));
    }

    /** @return array<int, string> */
    private function tenantUserIds(string $tenantId): array
    {
        if (!array_key_exists($tenantId, $this->tenantUserIdCache)) {
            $this->tenantUserIdCache[$tenantId] = array_values(array_unique(array_map(
                'strval',
                Db::name('sys_user')->where('TENANT_ID', $tenantId)->column('ID')
            )));
        }

        return $this->tenantUserIdCache[$tenantId];
    }

    private function applyTimeRange($query, array $filters, string $column, string $startKey, string $endKey): void
    {
        $start = trim((string)($filters[$startKey] ?? ''));
        $end = trim((string)($filters[$endKey] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime($column, $start, $end);
        }
    }

    private function applyHistoryVariableLike($query, string $name, mixed $value): void
    {
        $keyword = trim((string)($value ?? ''));
        if ($keyword === '') {
            return;
        }

        $ids = ActHiVarinst::where('NAME_', $name)
            ->whereLike('TEXT_', '%' . $keyword . '%')
            ->column('PROC_INST_ID_');
        if ($ids === []) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereIn('PROC_INST_ID_', array_values(array_unique(array_map('strval', $ids))));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function historicProcessRows(array $rows): array
    {
        $ids = array_values(array_filter(array_map(
            fn (array $row): string => (string)($row['PROC_INST_ID_'] ?? $row['ID_'] ?? ''),
            $rows
        )));
        $variables = $this->historyVariablesByProcess($ids);

        return array_map(fn (array $row): array => $this->processRow($row, $variables[(string)($row['PROC_INST_ID_'] ?? $row['ID_'] ?? '')] ?? []), $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function taskRows(array $rows, bool $runtimeVariables): array
    {
        $ids = array_values(array_filter(array_map(
            fn (array $row): string => (string)($row['PROC_INST_ID_'] ?? ''),
            $rows
        )));
        $variables = $runtimeVariables ? $this->runtimeVariablesByProcess($ids) : $this->historyVariablesByProcess($ids);

        return array_map(function (array $row) use ($variables): array {
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            $taskId = (string)($row['ID_'] ?? '');
            $record = $this->processRow($row, $variables[$processId] ?? []);
            // Task pages pass record.id as the task id; process ids stay on the instance aliases.
            $record['id'] = $taskId !== '' ? $taskId : ($record['id'] ?? $processId);
            $record['taskId'] = $taskId !== '' ? $taskId : null;
            $record['instanceId'] = $processId;
            $record['processInstanceId'] = $processId;
            $record['processId'] = $processId;

            return $record;
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runtimeProcessRows(array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        $rows = ActRuExecution::whereIn('PROC_INST_ID_', $processIds)
            ->order('ID_', 'desc')
            ->select()
            ->toArray();
        $byProcess = [];
        foreach ($rows as $row) {
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            if ($processId === '' || isset($byProcess[$processId])) {
                continue;
            }
            $byProcess[$processId] = $row;
        }

        $variables = $this->runtimeVariablesByProcess($processIds);

        return array_map(function (string $processId) use ($byProcess, $variables): array {
            $row = $byProcess[$processId] ?? ['ID_' => $processId, 'PROC_INST_ID_' => $processId];

            return $this->processRow($row, $variables[$processId] ?? []);
        }, $processIds);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function processRow(array $row, array $variables): array
    {
        $processId = (string)($row['PROC_INST_ID_'] ?? $row['ID_'] ?? '');
        $processKey = (string)($row['PROC_DEF_KEY_'] ?? $this->definitionKey((string)($row['PROC_DEF_ID_'] ?? '')) ?? '');
        $startUserId = trim((string)($row['START_USER_ID_'] ?? $variables['initiator'] ?? ''));
        $startUser = $this->userById($startUserId);
        $orgId = trim((string)($variables['org'] ?? $startUser['orgId'] ?? ''));
        $orgName = $this->orgName($orgId);
        $processLabel = WorkflowTitleFormatter::processLabel($processKey);
        $title = WorkflowTitleFormatter::displayTitle(
            isset($variables['title']) ? (string)$variables['title'] : null,
            $processKey,
            isset($startUser['name']) ? (string)$startUser['name'] : null
        );

        return array_merge($row, [
            'id' => $processId,
            'instanceId' => $processId,
            'processInstanceId' => $processId,
            'category' => $processKey,
            'processKey' => $processKey,
            'categoryName' => $processLabel,
            'processCategory' => $processKey,
            'processCategoryName' => $processLabel,
            'title' => $title,
            'status' => $variables['status'] ?? ($row['STATE_'] ?? null),
            'remark' => $variables['remark'] ?? null,
            'amount' => $variables['amount'] ?? null,
            'org' => $orgId !== '' ? $orgId : null,
            'orgId' => $orgId !== '' ? $orgId : null,
            'orgName' => $orgName,
            'createTime' => $row['START_TIME_'] ?? $row['CREATE_TIME_'] ?? null,
            'startTime' => $row['START_TIME_'] ?? $row['CREATE_TIME_'] ?? null,
            'endTime' => $row['END_TIME_'] ?? null,
            'startUserId' => $startUserId !== '' ? $startUserId : null,
            'promoterId' => $startUserId !== '' ? $startUserId : null,
            'promoterName' => $startUser['name'] ?? null,
            'startUserName' => $startUser['name'] ?? null,
            'variable' => $variables,
        ]);
    }

    /**
     * List endpoints expose only navigation metadata. Full workflow variables
     * remain behind the participant-authorized process detail endpoint.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function processSummaryRows(array $rows, bool $includeListValues = false): array
    {
        $summaryKeys = array_fill_keys([
            'id',
            'instanceId',
            'processInstanceId',
            'category',
            'processKey',
            'categoryName',
            'processCategory',
            'processCategoryName',
            'title',
            'status',
            'createTime',
            'startTime',
            'endTime',
            'startUserId',
        ], true);

        return array_map(static function (array $row) use ($summaryKeys, $includeListValues): array {
            $summary = array_intersect_key($row, $summaryKeys);
            if ($includeListValues) {
                $summary['remark'] = $row['remark'] ?? null;
                $summary['amount'] = $row['amount'] ?? null;
                // The current table reads variable.amount. Preserve only this
                // display value; never return the complete workflow payload.
                $summary['variable'] = ['amount' => $row['amount'] ?? null];
            }

            return $summary;
        }, $rows);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function historyVariablesByProcess(array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        return $this->variablesByProcess(
            ActHiVarinst::whereIn('PROC_INST_ID_', $processIds)
                ->order(['CREATE_TIME_' => 'asc', 'ID_' => 'asc'])
                ->select()
                ->toArray()
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runtimeVariablesByProcess(array $processIds): array
    {
        if ($processIds === []) {
            return [];
        }

        return $this->variablesByProcess(
            ActRuVariable::whereIn('PROC_INST_ID_', $processIds)
                ->order(['ID_' => 'asc'])
                ->select()
                ->toArray()
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function variablesByProcess(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            if ($processId === '') {
                continue;
            }
            $grouped[$processId][] = $row;
        }

        foreach ($grouped as $processId => $processRows) {
            $grouped[$processId] = $this->variableService->normalizeMap($processRows);
        }

        return $grouped;
    }

    /**
     * @return array<int, string>
     */
    private function runtimeProcessIdsByVariable(string $name, mixed $value, string $processCategory = ''): array
    {
        $rows = ActRuVariable::where('NAME_', $name)->select()->toArray();
        $ids = [];
        foreach ($rows as $row) {
            if (!$this->sameValue($this->variableService->normalizeMap([$row])[$name] ?? null, $value)) {
                continue;
            }
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            if ($processId !== '') {
                $ids[] = $processId;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === [] || $processCategory === '') {
            return $ids;
        }

        return array_values(array_filter($ids, fn (string $id): bool => $this->runtimeProcessKey($id) === $processCategory));
    }

    /**
     * @return array<int, string>
     */
    private function historyProcessIdsByVariable(string $name, mixed $value): array
    {
        $rows = ActHiVarinst::where('NAME_', $name)->select()->toArray();
        $ids = [];
        foreach ($rows as $row) {
            if (!$this->sameValue($this->variableService->normalizeMap([$row])[$name] ?? null, $value)) {
                continue;
            }
            $processId = (string)($row['PROC_INST_ID_'] ?? '');
            if ($processId !== '') {
                $ids[] = $processId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $processIds
     * @param array<int, mixed> $names
     * @return array<string, array<string, mixed>>
     */
    private function runtimeVariableMapForNames(array $processIds, array $names): array
    {
        if ($processIds === [] || $names === []) {
            return [];
        }

        $rows = ActRuVariable::whereIn('PROC_INST_ID_', $processIds)
            ->whereIn('NAME_', array_map('strval', $names))
            ->select()
            ->toArray();

        return $this->variablesByProcess($rows);
    }

    private function runtimeProcessKey(string $processId): ?string
    {
        $definitionId = ActRuExecution::where('PROC_INST_ID_', $processId)->value('PROC_DEF_ID_');

        return is_string($definitionId) ? $this->definitionKey($definitionId) : null;
    }

    private function definitionKey(string $definitionId): ?string
    {
        if ($definitionId === '') {
            return null;
        }

        $key = ActReProcdef::where('ID_', $definitionId)->value('KEY_');
        if (is_string($key) && $key !== '') {
            return $key;
        }

        return str_contains($definitionId, ':') ? explode(':', $definitionId)[0] : $definitionId;
    }

    /**
     * @param array<int, array<string, mixed>> $activities
     * @return array<string, array<string, mixed>>
     */
    private function activityVariableMap(string $processInstanceId): array
    {
        $rows = ActHiVarinst::where('PROC_INST_ID_', $processInstanceId)
            ->whereIn('NAME_', ['approval', 'comment', 'state', 'status'])
            ->order(['CREATE_TIME_' => 'asc', 'ID_' => 'asc'])
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($rows as $row) {
            $activityId = (string)($row['ACT_INST_ID_'] ?? '');
            if ($activityId === '') {
                continue;
            }
            $grouped[$activityId][] = $row;
        }

        foreach ($grouped as $activityId => $activityRows) {
            $grouped[$activityId] = $this->variableService->normalizeMap($activityRows);
        }

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     * @param array<string, array<string, mixed>> $activityVariables
     * @return array<int, array<string, mixed>>
     */
    private function userActivityList(array $activities, array $comments, array $activityVariables = []): array
    {
        $commentsByTask = [];
        foreach ($comments as $comment) {
            $taskId = (string)($comment['TASK_ID_'] ?? '');
            if ($taskId === '') {
                continue;
            }
            $commentsByTask[$taskId][] = $comment;
        }

        return array_values(array_map(function (array $activity) use ($commentsByTask, $activityVariables): array {
            $taskId = (string)($activity['TASK_ID_'] ?? '');
            $userId = (string)($activity['ASSIGNEE_'] ?? '');
            $comment = $commentsByTask[$taskId][0] ?? [];
            $activityId = (string)($activity['ID_'] ?? '');
            $form = $activityVariables[$activityId] ?? [];
            $approval = $form['approval'] ?? null;
            $state = $form['state'] ?? $form['status'] ?? null;
            if ($state === null && is_bool($approval)) {
                $state = $approval ? 'AGREE' : 'REJECT';
            }

            return [
                'category' => $activity['ACT_ID_'] ?? null,
                'name' => $activity['ACT_NAME_'] ?? $activity['ACT_ID_'] ?? null,
                'taskDetailList' => $taskId === '' ? [] : [[
                    'taskId' => $taskId,
                    'bizUser' => $this->userById($userId),
                    'form' => [
                        'comment' => $comment['MESSAGE_'] ?? ($form['comment'] ?? null),
                        'state' => $state,
                        'status' => $form['status'] ?? $state,
                        'approval' => $approval,
                    ],
                    'startTime' => $activity['START_TIME_'] ?? null,
                    'endTime' => $activity['END_TIME_'] ?? null,
                ]],
            ];
        }, array_filter($activities, fn (array $activity): bool => (string)($activity['ACT_TYPE_'] ?? '') === 'userTask')));
    }

    /**
     * @return array<string, mixed>
     */
    private function userById(string $userId): array
    {
        if ($userId === '') {
            return [];
        }
        if (array_key_exists($userId, $this->userCache)) {
            return $this->userCache[$userId];
        }

        $row = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ID, ACCOUNT, NAME, AVATAR, ORG_ID')
            ->find();
        if (!is_array($row) || $row === []) {
            return $this->userCache[$userId] = ['id' => $userId, 'name' => null, 'avatar' => null];
        }

        return $this->userCache[$userId] = [
            'id' => $row['ID'] ?? $userId,
            'account' => $row['ACCOUNT'] ?? null,
            'name' => $row['NAME'] ?? null,
            'avatar' => $row['AVATAR'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
        ];
    }

    private function orgName(string $orgId): ?string
    {
        if ($orgId === '') {
            return null;
        }
        if (array_key_exists($orgId, $this->orgNameCache)) {
            return $this->orgNameCache[$orgId];
        }

        $name = Db::name('sys_org')
            ->where('ID', $orgId)
            ->value('NAME');

        return $this->orgNameCache[$orgId] = is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param array<int, mixed> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function usersByIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $userIds))));
        if ($ids === []) {
            return [];
        }

        return array_map(fn (string $id): array => $this->userById($id), $ids);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orgTree(string $orgId): array
    {
        if ($orgId === '') {
            return [];
        }

        $row = Db::name('sys_org')
            ->where('ID', $orgId)
            ->field('ID, PARENT_ID, NAME')
            ->find();
        if (!is_array($row) || $row === []) {
            return [];
        }

        return [[
            'id' => $row['ID'] ?? $orgId,
            'parentId' => $row['PARENT_ID'] ?? null,
            'label' => $row['NAME'] ?? null,
            'name' => $row['NAME'] ?? null,
            'children' => [],
        ]];
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

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string)$item)),
            $value
        ))));
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn (mixed $item): bool => $item !== null && $item !== ''));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== ''));
        }

        return $value === null || $value === '' ? [] : [$value];
    }

    private function sameValue(mixed $left, mixed $right): bool
    {
        if (is_scalar($left) && is_scalar($right)) {
            return (string)$left === (string)$right;
        }

        return $left === $right;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['size'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
