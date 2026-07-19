<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowRuntimeService;
use app\service\workflow\WorkflowVariableService;
use app\service\biz\CollectionReceiptService;
use app\service\biz\DebitNoteService;
use app\service\biz\FileRelationService;
use app\service\biz\PurchaseOrderService;
use app\service\biz\ReturnOrderService;
use app\service\biz\SaleProjectProductItemRelationService;
use app\service\biz\SaleProjectService;
use app\support\ApiResponse;
use RuntimeException;
use think\facade\Db;
use think\Request;
use think\Response;

class ProcessController extends BaseWorkflowController
{
    public function __construct(
        private readonly WorkflowQueryService $workflowQueryService = new WorkflowQueryService(),
        private readonly WorkflowRuntimeService $workflowRuntimeService = new WorkflowRuntimeService(),
        private readonly WorkflowVariableService $workflowVariableService = new WorkflowVariableService(),
        private readonly FileRelationService $fileRelationService = new FileRelationService(),
        private readonly SaleProjectService $saleProjectService = new SaleProjectService(),
        private readonly PurchaseOrderService $purchaseOrderService = new PurchaseOrderService(),
        private readonly DebitNoteService $debitNoteService = new DebitNoteService(),
        private readonly CollectionReceiptService $collectionReceiptService = new CollectionReceiptService(),
        private readonly ReturnOrderService $returnOrderService = new ReturnOrderService(),
        private readonly SaleProjectProductItemRelationService $saleProjectProductItemRelationService = new SaleProjectProductItemRelationService()
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
        return $this->guard(function () use ($request): array {
            $filters = $request->get();
            $payload = $this->authPayload($request);
            $this->assertProjectVariableReadable($filters, $payload);
            $this->assertObjectVariableReadable($filters, $payload);

            return $this->workflowQueryService->queryProcess($filters, $payload);
        });
    }

    public function queryList(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $filters = $this->body($request);
            $payload = $this->authPayload($request);
            $this->assertPurchaseOrderProcessListReadable($filters, $payload);

            return $this->workflowQueryService->queryProcessList($filters, $payload);
        });
    }

    public function projectRuntimeQueryList(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $filters = $request->get();
            $projectId = trim((string)($filters['projectId'] ?? ''));
            $payload = $this->authPayload($request);
            $this->saleProjectService->assertReadable($projectId, $payload);

            return $this->workflowQueryService->projectRuntimeQueryList($filters, $payload);
        });
    }

    public function projectProductItemRelationList(Request $request): Response
    {
        $input = $this->body($request);

        return $this->guard(function () use ($request, $input): array {
            $processInstanceId = $this->processInstanceId($request, $input);
            $payload = $this->authPayload($request);
            $this->workflowQueryService->assertProcessReadable($processInstanceId, $payload);

            $variables = $this->workflowVariableService->historyByProcessInstance($processInstanceId);
            $projectId = trim((string)($variables['projectId'] ?? $variables['bizSaleProjectId'] ?? ''));
            $objectIds = $this->stringList($input['objectIds'] ?? []);
            $allowedObjectIds = $this->workflowProjectProductItemIds($variables);
            if ($projectId === '' || array_diff($objectIds, $allowedObjectIds) !== []) {
                throw new RuntimeException('permission denied', 403);
            }

            return $this->saleProjectProductItemRelationService->listForWorkflowProject(
                $objectIds,
                $projectId,
                $payload
            );
        });
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
     * `/biz/process/query` is shared by several modules. Project-variable
     * lookups must not turn a guessed project ID into a workflow-data oracle.
     */
    private function assertProjectVariableReadable(array $filters, array $payload): void
    {
        $variableName = strtolower(trim((string)($filters['variableName'] ?? '')));
        if (!in_array($variableName, ['projectid', 'bizsaleprojectid'], true)) {
            return;
        }

        $values = $this->stringList($filters['variable'] ?? $filters['variableList'] ?? []);
        foreach ($values as $projectId) {
            $this->saleProjectService->assertReadable($projectId, $payload);
        }
    }

    /**
     * Legacy list pages use objectId for several non-project workflows. Resolve
     * each object to its owning module and reuse that module's page/detail data
     * scope before exposing matching process IDs.
     */
    private function assertObjectVariableReadable(array $filters, array $payload): void
    {
        if (strtolower(trim((string)($filters['variableName'] ?? ''))) !== 'objectid') {
            return;
        }

        $objectIds = $this->stringList($filters['variable'] ?? $filters['variableList'] ?? []);
        if ($objectIds === []) {
            return;
        }

        $typesByObjectId = [];
        foreach ([
            'biz_purchase_order' => 'purchaseOrder',
            'biz_debit_note' => 'debitNote',
            'biz_collection_receipt' => 'collectionReceipt',
            'return_order' => 'returnOrder',
        ] as $table => $objectType) {
            $ids = Db::name($table)
                ->whereIn('ID', $objectIds)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
                })
                ->column('ID');
            foreach ($ids as $id) {
                $typesByObjectId[(string)$id][] = $objectType;
            }
        }

        foreach ($objectIds as $objectId) {
            $objectTypes = $typesByObjectId[$objectId] ?? [];
            if (count($objectTypes) !== 1) {
                throw new RuntimeException('permission denied', 403);
            }

            $this->assertResolvedObjectReadable($objectTypes[0], $objectId, $payload);
        }
    }

    private function assertResolvedObjectReadable(string $objectType, string $objectId, array $payload): void
    {
        match ($objectType) {
            'purchaseOrder' => $this->purchaseOrderService->assertReadable($objectId, $payload),
            'debitNote' => $this->debitNoteService->assertReadable($objectId, $payload),
            'collectionReceipt' => $this->collectionReceiptService->assertReadable($objectId, $payload),
            'returnOrder' => $this->returnOrderService->assertReadable($objectId, $payload),
            default => throw new RuntimeException('permission denied', 403),
        };
    }

    /**
     * The only supported query-list consumer is the purchase-order process
     * tab. Bind the generic workflow lookup to that business object before
     * returning even summary process metadata.
     */
    private function assertPurchaseOrderProcessListReadable(array $filters, array $payload): void
    {
        $attributes = $filters['attribute'] ?? [];
        if (!is_array($attributes) || array_keys($attributes) !== ['objectId']) {
            throw new RuntimeException('invalid attribute', 400);
        }

        $objectId = trim((string)($attributes['objectId'] ?? ''));
        $processKeys = $this->stringList(
            $filters['processKeyList'] ?? $filters['processKeys'] ?? $filters['category'] ?? []
        );
        $allowedProcessKeys = [
            'Process_procure_in_warehouse',
            'Process_reimbursement',
            'Process_make_payment',
        ];
        if (
            $objectId === ''
            || $processKeys === []
            || array_diff($processKeys, $allowedProcessKeys) !== []
        ) {
            throw new RuntimeException('invalid process query', 400);
        }

        $this->purchaseOrderService->assertReadable($objectId, $payload);
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function withDisplayVariables(array $variables, string $processInstanceId): array
    {
        $projectId = trim((string)($variables['projectId'] ?? $variables['bizSaleProjectId'] ?? ''));
        if ($projectId !== '' && !isset($variables['projectName'])) {
            $projectName = $this->saleProjectNameForProcess($projectId, $variables, $processInstanceId);
            if ($projectName !== '') {
                $variables['projectName'] = $projectName;
            }
        }

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
    private function saleProjectNameForProcess(
        string $projectId,
        array $variables,
        string $processInstanceId
    ): string {
        $tenantId = $this->processTenantId($variables, $processInstanceId);
        if ($tenantId === '') {
            return '';
        }

        return trim((string)Db::name('biz_sale_project')
            ->where('ID', $projectId)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->value('PROJECT_NAME'));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function settlementAccountNameForProcess(string $accountId, array $variables, string $processInstanceId): string
    {
        $tenantId = $this->processTenantId($variables, $processInstanceId);

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
     * @param array<string, mixed> $variables
     */
    private function processTenantId(array $variables, string $processInstanceId): string
    {
        $tenantId = trim((string)($variables['tenantId'] ?? ''));
        if ($tenantId !== '') {
            return $tenantId;
        }

        return trim((string)Db::name('act_hi_procinst')
            ->where('PROC_INST_ID_', $processInstanceId)
            ->value('TENANT_ID_'));
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<int, string>
     */
    private function workflowProjectProductItemIds(array $variables): array
    {
        $ids = [];
        foreach (['projectProductItemList', 'productList'] as $variableName) {
            $items = $variables[$variableName] ?? [];
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = trim((string)(
                    $item['projectProductItemId']
                    ?? $item['project_product_item_id']
                    ?? $item['PROJECT_PRODUCT_ITEM_ID']
                    ?? ''
                ));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
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
