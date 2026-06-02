<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowVariableService;
use app\service\biz\FileRelationService;
use think\Request;
use think\Response;

class ProcessController extends BaseWorkflowController
{
    public function __construct(
        private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService(),
        private readonly WorkflowVariableService $workflowVariableService = new WorkflowVariableService(),
        private readonly FileRelationService $fileRelationService = new FileRelationService()
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
            $this->processInstanceId($request)
        ));
    }

    public function variable(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowVariableService->historyByProcessInstance(
            $this->processInstanceId($request)
        ));
    }

    public function allPage(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->allProcessPage(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    public function query(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->queryProcess($request->get()));
    }

    public function queryList(Request $request): Response
    {
        $filters = $request->post();
        if ($filters === []) {
            $filters = $request->param();
        }

        return $this->guard(fn () => $this->workflowQueryService->queryProcessList($filters));
    }

    public function projectRuntimeQueryList(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->projectRuntimeQueryList($request->get()));
    }

    public function fileList(Request $request): Response
    {
        $filters = $request->post();
        if ($filters === []) {
            $filters = $request->param();
        }

        return $this->guard(fn () => $this->fileRelationService->list([
            'objectId' => $this->requiredString($request, 'id'),
            'category' => $filters['category'] ?? null,
        ], $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function processInstanceId(Request $request): string
    {
        $value = trim((string)$request->param('processInstanceId', ''));
        if ($value !== '') {
            return $value;
        }

        return $this->requiredString($request, 'id');
    }
}
