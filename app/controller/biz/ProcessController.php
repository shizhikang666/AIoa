<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowRuntimeService;
use app\service\workflow\WorkflowVariableService;
use app\service\biz\FileRelationService;
use app\support\ApiResponse;
use think\facade\Db;
use think\Request;
use think\Response;

class ProcessController extends BaseWorkflowController
{
    public function __construct(
        private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService(),
        private readonly WorkflowRuntimeService $workflowRuntimeService = new WorkflowRuntimeService(),
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
        return $this->guard(function () use ($request): array {
            $processInstanceId = $this->processInstanceId($request);
            $payload = $this->authPayload($request);
            $this->workflowQueryService->assertProcessReadable($processInstanceId, $payload);

            return $this->workflowQueryService->processDetail(
                $processInstanceId,
                $this->currentUserId($request)
            );
        });
    }

    public function variable(Request $request): Response
    {
        $input = $this->body($request);

        return $this->guard(function () use ($request, $input): array {
            $processInstanceId = $this->processInstanceId($request, $input);
            $this->workflowQueryService->assertProcessReadable(
                $processInstanceId,
                $this->authPayload($request)
            );
            $variables = $this->workflowVariableService->historyByProcessInstance(
                $processInstanceId
            );
            $fields = $this->stringList($input['fields'] ?? []);
            if ($fields !== []) {
                $variables = array_intersect_key($variables, array_flip($fields));
            }
            $variables = $this->withDisplayVariables($variables, $processInstanceId);

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

        return $this->guard(function () use ($request, $filters): array {
            $processInstanceId = $this->processInstanceId($request, $filters);
            $payload = $this->authPayload($request);
            $this->workflowQueryService->assertProcessReadable($processInstanceId, $payload);

            return $this->fileRelationService->list([
                'objectId' => $processInstanceId,
                'category' => $filters['category'] ?? null,
            ], $payload);
        });
    }

    public function cancel(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->cancelProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function leaveEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->editLeaveProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function leaveStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startLeaveProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function makePaymentStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startGeneralProcess(
            'Process_make_payment',
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function paymentStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startGeneralProcess(
            'Process_payment',
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function procureStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startGeneralProcess(
            'Process_procure',
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function procureWarehouseStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startGeneralProcess(
            'Process_procure_in_warehouse',
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function projectDeliveryStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startProjectDeliveryProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function projectInitStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startProjectInitProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function projectPlayStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startProjectPlayProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function projectReissueStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startProjectReissueProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function projectReturnStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startProjectReturnProcess(
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function reimbursementStart(Request $request): Response
    {
        return $this->guard(fn () => $this->workflowRuntimeService->startGeneralProcess(
            'Process_reimbursement',
            $this->body($request),
            $this->authPayload($request)
        ));
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
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function withDisplayVariables(array $variables, string $processInstanceId): array
    {
        $accountId = trim((string)($variables['accountId'] ?? ''));
        if ($accountId === '' || isset($variables['accountName'])) {
            return $variables;
        }

        $accountName = $this->settlementAccountNameForProcess($accountId, $variables, $processInstanceId);
        if ($accountName !== '') {
            $variables['accountName'] = $accountName;
        }

        return $variables;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function settlementAccountNameForProcess(string $accountId, array $variables, string $processInstanceId): string
    {
        $tenantId = trim((string)($variables['tenantId'] ?? ''));
        if ($tenantId === '') {
            $tenantId = trim((string)Db::name('act_hi_procinst')
                ->where('PROC_INST_ID_', $processInstanceId)
                ->value('TENANT_ID_'));
        }

        $query = Db::name('settlement_account')
            ->where('ID', $accountId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            });

        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return trim((string)$query->value('ACCOUNT_NAME'));
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
