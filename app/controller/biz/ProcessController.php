<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowVariableService;
use app\service\biz\FileRelationService;
use app\support\ApiResponse;
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
        $input = $this->body($request);

        return $this->guard(function () use ($request, $input): array {
            $variables = $this->workflowVariableService->historyByProcessInstance(
                $this->processInstanceId($request, $input)
            );
            $fields = $this->stringList($input['fields'] ?? []);
            if ($fields !== []) {
                $variables = array_intersect_key($variables, array_flip($fields));
            }

            return array_map(
                fn (string $name, mixed $value): array => [
                    'name' => $name,
                    'value' => $value,
                    'label' => $name,
                    'type' => is_array($value) ? 'json' : get_debug_type($value),
                    'properties' => [],
                ],
                array_keys($variables),
                array_values($variables)
            );
        });
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
        return $this->guard(fn () => $this->workflowQueryService->queryProcessList($this->body($request)));
    }

    public function projectRuntimeQueryList(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowQueryService->projectRuntimeQueryList($request->get()));
    }

    public function fileList(Request $request): Response
    {
        $filters = $this->body($request);

        return $this->guard(fn () => $this->fileRelationService->list([
            'objectId' => $this->processInstanceId($request, $filters),
            'category' => $filters['category'] ?? null,
        ], $this->authPayload($request)));
    }

    public function cancel(): Response
    {
        return $this->deferredWrite('process cancel');
    }

    public function leaveEdit(): Response
    {
        return $this->deferredWrite('leave process edit');
    }

    public function leaveStart(): Response
    {
        return $this->deferredWrite('leave process start');
    }

    public function makePaymentStart(): Response
    {
        return $this->deferredWrite('make payment process start');
    }

    public function paymentStart(): Response
    {
        return $this->deferredWrite('payment process start');
    }

    public function procureStart(): Response
    {
        return $this->deferredWrite('procure process start');
    }

    public function procureWarehouseStart(): Response
    {
        return $this->deferredWrite('procure warehouse process start');
    }

    public function projectDeliveryStart(): Response
    {
        return $this->deferredWrite('project delivery process start');
    }

    public function projectInitStart(): Response
    {
        return $this->deferredWrite('project init process start');
    }

    public function projectPlayStart(): Response
    {
        return $this->deferredWrite('project play process start');
    }

    public function projectReissueStart(): Response
    {
        return $this->deferredWrite('project reissue process start');
    }

    public function projectReturnStart(): Response
    {
        return $this->deferredWrite('project return process start');
    }

    public function reimbursementStart(): Response
    {
        return $this->deferredWrite('reimbursement process start');
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

    private function processInstanceId(Request $request, array $input = []): string
    {
        $value = trim((string)($input['processInstanceId'] ?? $request->param('processInstanceId', '')));
        if ($value !== '') {
            return $value;
        }

        $value = trim((string)($input['id'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return $this->requiredString($request, 'id');
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
}
