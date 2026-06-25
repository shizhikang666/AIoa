<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowRuntimeService;
use app\support\ApiResponse;
use think\Request;
use think\Response;

class TaskController extends BaseWorkflowController
{
    public function __construct(
        private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService(),
        private readonly WorkflowRuntimeService $workflowRuntimeService = new WorkflowRuntimeService()
    ) {
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

    public function approve(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->approveTask(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function reject(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->rejectTask(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function sseStream(): Response
    {
        return $this->deferredWrite('task sse stream');
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail($operation . ' is deferred', 400, [
            'operation' => $operation,
        ]);
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function body(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $raw = '';
        if (method_exists($request, 'getContent')) {
            $raw = trim((string)$request->getContent());
        }
        if ($raw === '' && method_exists($request, 'getInput')) {
            $raw = trim((string)$request->getInput());
        }
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
