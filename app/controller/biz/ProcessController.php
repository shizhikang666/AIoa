<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowVariableService;
use think\Request;
use think\Response;

class ProcessController extends BaseWorkflowController
{
    public function __construct(
        private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService(),
        private readonly WorkflowVariableService $workflowVariableService = new WorkflowVariableService()
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->startedProcessPage(
            $this->currentUserId($request),
            $request->get()
        ));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->processDetail(
            $this->requiredString($request, 'processInstanceId')
        ));
    }

    public function variable(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowVariableService->historyByProcessInstance(
            $this->requiredString($request, 'processInstanceId')
        ));
    }
}
