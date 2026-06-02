<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use think\Request;
use think\Response;

class TaskController extends BaseWorkflowController
{
    public function __construct(private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService())
    {
    }

    public function count(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->pendingTaskCount($this->currentUserId($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->pendingTaskList(
            $this->currentUserId($request),
            $request->get()
        ));
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->pendingTaskPage(
            $this->currentUserId($request),
            $request->get()
        ));
    }

    public function historyPage(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->historyTaskPage(
            $this->currentUserId($request),
            $request->get()
        ));
    }

    public function runtimeActivityDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->runtimeActivityDetail(
            $this->requiredString($request, 'id'),
            $this->currentUserId($request)
        ));
    }
}
