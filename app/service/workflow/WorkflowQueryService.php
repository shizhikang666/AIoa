<?php

declare(strict_types=1);

namespace app\service\workflow;

use app\model\ActHiActinst;
use app\model\ActHiComment;
use app\model\ActHiProcinst;
use app\model\ActHiTaskinst;
use app\model\ActRuTask;

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
        $records = $this->pendingTaskQuery($userId, $filters)
            ->order(['CREATE_TIME_' => 'desc', 'ID_' => 'desc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingTaskList(string $userId, array $filters = []): array
    {
        return $this->pendingTaskQuery($userId, $filters)
            ->order(['CREATE_TIME_' => 'desc', 'ID_' => 'desc'])
            ->select()
            ->toArray();
    }

    public function historyTaskPage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->historyTaskQuery($userId, $filters)->count();
        $records = $this->historyTaskQuery($userId, $filters)
            ->order(['END_TIME_' => 'desc', 'START_TIME_' => 'desc', 'ID_' => 'desc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function startedProcessPage(string $userId, array $filters = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->startedProcessQuery($userId, $filters)->count();
        $records = $this->startedProcessQuery($userId, $filters)
            ->order(['START_TIME_' => 'desc', 'ID_' => 'desc'])
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function processDetail(string $processInstanceId): array
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

        return [
            'process' => $process ? $process->toArray() : null,
            'variables' => $this->variableService->historyByProcessInstance($processInstanceId),
            'activities' => $activities,
            'comments' => $comments,
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

    private function historyTaskQuery(string $userId, array $filters)
    {
        $query = ActHiTaskinst::where('ASSIGNEE_', $userId);

        if (!empty($filters['processInstanceId'])) {
            $query->where('PROC_INST_ID_', (string)$filters['processInstanceId']);
        }

        if (!empty($filters['processKey'])) {
            $query->where('PROC_DEF_KEY_', (string)$filters['processKey']);
        }

        return $query;
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

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? $filters['current'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }
}
