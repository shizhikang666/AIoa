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
use RuntimeException;
use think\facade\Db;

/**
 * Read-only workflow query service for Camunda-compatible process/task data.
 */
class WorkflowQueryService
{
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

    public function allProcessPage(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->historicProcessQuery($filters, $payload)->count();
        $records = $this->historicProcessRows(
            $this->historicProcessQuery($filters, $payload)
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function queryProcess(array $filters = []): array
    {
        $variableName = trim((string)($filters['variableName'] ?? ''));
        if ($variableName === '') {
            throw new RuntimeException('missing variableName', 400);
        }

        $values = $this->arrayValue($filters['variable'] ?? $filters['variableList'] ?? []);
        $findValue = $this->arrayValue($filters['findValue'] ?? []);
        $processCategory = trim((string)($filters['processCategory'] ?? $filters['category'] ?? ''));

        return array_map(function (mixed $value) use ($variableName, $findValue, $processCategory): array {
            $processIds = $this->runtimeProcessIdsByVariable($variableName, $value, $processCategory);

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
    public function queryProcessList(array $filters = []): array
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

        $query = ActHiProcinst::where([]);
        if ($processKeys !== []) {
            $query->whereIn('PROC_DEF_KEY_', array_map('strval', $processKeys));
        }
        if (is_array($processIds)) {
            if ($processIds === []) {
                return [];
            }
            $query->whereIn('PROC_INST_ID_', $processIds);
        }

        return $this->historicProcessRows(
            $query->order(['START_TIME_' => 'desc', 'ID_' => 'desc'])
                ->select()
                ->toArray()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectRuntimeQueryList(array $filters = []): array
    {
        $projectId = trim((string)($filters['projectId'] ?? ''));
        if ($projectId === '') {
            throw new RuntimeException('missing projectId', 400);
        }

        $processIds = $this->runtimeProcessIdsByVariable('projectId', $projectId);

        return $this->runtimeProcessRows($processIds);
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
        $query = ActHiProcinst::where([]);

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID_', $tenantId);
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

        return array_merge($row, [
            'id' => $processId,
            'instanceId' => $processId,
            'processInstanceId' => $processId,
            'category' => $processKey,
            'processKey' => $processKey,
            'title' => $variables['title'] ?? null,
            'status' => $variables['status'] ?? ($row['STATE_'] ?? null),
            'remark' => $variables['remark'] ?? null,
            'amount' => $variables['amount'] ?? null,
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

        $row = Db::name('sys_user')
            ->where('ID', $userId)
            ->field('ID, ACCOUNT, NAME, AVATAR, ORG_ID')
            ->find();
        if (!is_array($row) || $row === []) {
            return ['id' => $userId, 'name' => null, 'avatar' => null];
        }

        return [
            'id' => $row['ID'] ?? $userId,
            'account' => $row['ACCOUNT'] ?? null,
            'name' => $row['NAME'] ?? null,
            'avatar' => $row['AVATAR'] ?? null,
            'orgId' => $row['ORG_ID'] ?? null,
        ];
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
