#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\controller\biz\ProcessController;
use app\service\biz\BizDraftService;
use app\service\biz\BizUserVacationService;
use app\service\biz\CollectionReceiptService;
use app\service\biz\DebitNoteService;
use app\service\biz\FileRelationService;
use app\service\biz\ProductService;
use app\service\biz\PurchaseOrderService;
use app\service\biz\ReturnOrderService;
use app\service\biz\SaleProjectService;
use app\service\biz\SaleProjectProductItemRelationService;
use app\service\workflow\WorkflowQueryService;
use app\service\workflow\WorkflowRuntimeService;
use app\service\workflow\WorkflowVariableService;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'app\\')) {
        return;
    }

    $file = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

function assertSameAuthorizationValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function assertAuthorizationDenied(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if ($exception->getCode() === 403) {
            return;
        }

        throw new RuntimeException($message . ': wrong exception code ' . $exception->getCode());
    }

    throw new RuntimeException($message . ': expected permission denial');
}

function assertAuthorizationBadRequest(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if ($exception->getCode() === 400) {
            return;
        }

        throw new RuntimeException($message . ': wrong exception code ' . $exception->getCode());
    }

    throw new RuntimeException($message . ': expected request rejection');
}

$spy = new class extends SaleProjectService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
    }
};
$purchaseOrderSpy = new class extends PurchaseOrderService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
    }
};
$debitNoteSpy = new class extends DebitNoteService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
    }
};
$collectionReceiptSpy = new class extends CollectionReceiptService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
    }
};
$returnOrderSpy = new class extends ReturnOrderService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
    }
};

$controller = new ProcessController(
    new WorkflowQueryService(),
    new WorkflowRuntimeService(),
    new WorkflowVariableService(),
    new FileRelationService(),
    $spy,
    $purchaseOrderSpy,
    $debitNoteSpy,
    $collectionReceiptSpy,
    $returnOrderSpy
);
$guard = new ReflectionMethod(ProcessController::class, 'assertProjectVariableReadable');
$payload = ['user_id' => 'user-1', 'tenant_id' => 'tenant-1'];
$guard->invoke($controller, [
    'variableName' => 'projectId',
    'variable' => 'project-1,project-2',
], $payload);

assertSameAuthorizationValue(
    [
        ['id' => 'project-1', 'payload' => $payload],
        ['id' => 'project-2', 'payload' => $payload],
    ],
    $spy->calls,
    'project process query applies project participant visibility guard'
);

$guard->invoke($controller, [
    'variableName' => 'objectId',
    'variable' => 'object-1',
], $payload);
assertSameAuthorizationValue(2, count($spy->calls), 'non-project query does not use project guard');

$objectVariableGuard = new ReflectionMethod(ProcessController::class, 'assertObjectVariableReadable');
$objectVariableGuard->invoke($controller, [
    'variableName' => 'objectId',
    'variable' => '',
], $payload);
assertSameAuthorizationValue([], $purchaseOrderSpy->calls, 'empty objectId list returns an empty workflow result');
assertSameAuthorizationValue([], $debitNoteSpy->calls, 'empty objectId list skips debit-note scope lookup');
assertSameAuthorizationValue([], $collectionReceiptSpy->calls, 'empty objectId list skips receipt scope lookup');
assertSameAuthorizationValue([], $returnOrderSpy->calls, 'empty objectId list skips return-order scope lookup');

$workflowProductIds = new ReflectionMethod(ProcessController::class, 'workflowProjectProductItemIds');
assertSameAuthorizationValue(
    ['project-item-1', 'project-item-2'],
    $workflowProductIds->invoke($controller, [
        'projectProductItemList' => [
            ['projectProductItemId' => 'project-item-1'],
        ],
        'productList' => [
            ['project_product_item_id' => 'project-item-2'],
            ['projectProductItemId' => 'project-item-1'],
        ],
    ]),
    'workflow product relation lookup is limited to item ids recorded in the process'
);

$objectGuard = new ReflectionMethod(ProcessController::class, 'assertResolvedObjectReadable');
foreach ([
    'purchaseOrder' => 'purchase-order-1',
    'debitNote' => 'debit-note-1',
    'collectionReceipt' => 'collection-receipt-1',
    'returnOrder' => 'return-order-1',
] as $objectType => $objectId) {
    $objectGuard->invoke($controller, $objectType, $objectId, $payload);
}
assertSameAuthorizationValue(
    [['id' => 'purchase-order-1', 'payload' => $payload]],
    $purchaseOrderSpy->calls,
    'objectId query dispatches purchase orders to the purchase-order scope guard'
);
assertSameAuthorizationValue(
    [['id' => 'debit-note-1', 'payload' => $payload]],
    $debitNoteSpy->calls,
    'objectId query dispatches debit notes to the debit-note scope guard'
);
assertSameAuthorizationValue(
    [['id' => 'collection-receipt-1', 'payload' => $payload]],
    $collectionReceiptSpy->calls,
    'objectId query dispatches collection receipts to the collection-receipt scope guard'
);
assertSameAuthorizationValue(
    [['id' => 'return-order-1', 'payload' => $payload]],
    $returnOrderSpy->calls,
    'objectId query dispatches return orders to the return-order scope guard'
);
assertAuthorizationDenied(
    static fn (): mixed => $objectGuard->invoke($controller, 'unknown', 'object-1', $payload),
    'objectId query rejects unsupported business-object types'
);
$purchaseOrderSpy->calls = [];

$purchaseOrderGuard = new ReflectionMethod(ProcessController::class, 'assertPurchaseOrderProcessListReadable');
$purchaseOrderGuard->invoke($controller, [
    'processKeyList' => ['Process_procure_in_warehouse', 'Process_reimbursement', 'Process_make_payment'],
    'attribute' => ['objectId' => 'purchase-order-1'],
], $payload);
assertSameAuthorizationValue(
    [['id' => 'purchase-order-1', 'payload' => $payload]],
    $purchaseOrderSpy->calls,
    'process query-list applies purchase-order visibility guard'
);
assertAuthorizationBadRequest(
    static fn (): mixed => $purchaseOrderGuard->invoke($controller, [
        'processKeyList' => ['Process_procure_in_warehouse'],
        'attribute' => ['objectId' => 'purchase-order-1', 'privateField' => 'probe'],
    ], $payload),
    'process query-list rejects arbitrary attributes'
);
assertAuthorizationBadRequest(
    static fn (): mixed => $purchaseOrderGuard->invoke($controller, [
        'processKeyList' => ['Process_sale_project_init'],
        'attribute' => ['objectId' => 'purchase-order-1'],
    ], $payload),
    'process query-list rejects unrelated process categories'
);

$tenantGuard = new ReflectionMethod(WorkflowQueryService::class, 'requiredPayloadTenantId');
$workflow = new WorkflowQueryService();
assertSameAuthorizationValue(
    'tenant-1',
    $tenantGuard->invoke($workflow, $payload),
    'tenant-scoped workflow query'
);
assertSameAuthorizationValue(
    null,
    $tenantGuard->invoke($workflow, ['account' => 'superAdmin']),
    'platform superadmin workflow query'
);
assertAuthorizationDenied(
    static fn (): mixed => $tenantGuard->invoke($workflow, ['user_id' => 'user-1']),
    'authenticated workflow query without tenant'
);

$saleProject = new SaleProjectService();
assertAuthorizationDenied(
    static fn (): mixed => $saleProject->assertReadable('project-1', []),
    'project auxiliary read without auth payload'
);
assertAuthorizationDenied(
    static fn (): mixed => $saleProject->assertReadable('', $payload),
    'project auxiliary read without project id'
);

foreach ([
    'debit note' => new DebitNoteService(),
    'collection receipt' => new CollectionReceiptService(),
    'return order' => new ReturnOrderService(),
] as $objectName => $service) {
    assertAuthorizationDenied(
        static fn (): mixed => $service->assertReadable('object-1', []),
        $objectName . ' read without auth payload'
    );
    assertAuthorizationDenied(
        static fn (): mixed => $service->assertReadable('', $payload),
        $objectName . ' read without object id'
    );
}

assertAuthorizationBadRequest(
    static fn (): mixed => $workflow->queryProcess([
        'variableName' => 'projectId',
        'variable' => 'project-1',
        'findValue' => 'privateForm',
    ], $payload),
    'workflow query arbitrary variable projection'
);

$denyingProjectGuard = new class extends SaleProjectService {
    public array $ids = [];

    public function assertDraftReadable(string $id, array $payload = []): void
    {
        $this->ids[] = $id;
        throw new RuntimeException('permission denied', 403);
    }

    public function assertDraftWritable(string $id, array $payload = []): void
    {
        $this->ids[] = $id;
        throw new RuntimeException('permission denied', 403);
    }
};
$draftService = new BizDraftService($denyingProjectGuard);
assertAuthorizationDenied(
    static fn (): mixed => $draftService->detail('project-denied', $payload),
    'draft detail outside project scope'
);
assertAuthorizationDenied(
    static fn (): mixed => $draftService->addOrEditSaleProjectDraft([
        'targetId' => 'project-denied',
        'extJson' => '{}',
    ], $payload),
    'draft save outside project scope'
);
assertSameAuthorizationValue(
    ['project-denied', 'project-denied'],
    $denyingProjectGuard->ids,
    'draft endpoints reuse project visibility guard'
);

$productService = new ProductService();
assertAuthorizationDenied(
    static fn (): mixed => $productService->children(['product-1'], []),
    'kit-product children without tenant payload'
);

$vacationService = new BizUserVacationService();
assertAuthorizationDenied(
    static fn (): mixed => $vacationService->detail([], []),
    'annual-leave balance read without tenant payload'
);
assertAuthorizationDenied(
    static fn (): mixed => $vacationService->detail(
        ['userId' => 'user-outside-scope'],
        ['user_id' => 'user-self', 'tenant_id' => 'tenant-1']
    ),
    'self-service annual-leave route cannot query another user'
);

$workflowRelationService = new SaleProjectProductItemRelationService();
assertAuthorizationDenied(
    static fn (): mixed => $workflowRelationService->listForWorkflowProject(
        ['project-item-1'],
        'project-1',
        []
    ),
    'workflow project-product relation read without tenant payload'
);

$processControllerSource = file_get_contents($root . '/app/controller/biz/ProcessController.php');
$processRelationMethod = new ReflectionMethod(ProcessController::class, 'projectProductItemRelationList');
$processControllerLines = is_string($processControllerSource)
    ? file($root . '/app/controller/biz/ProcessController.php')
    : false;
if (!is_array($processControllerLines)) {
    throw new RuntimeException('unable to read ProcessController source');
}
$processRelationSource = implode('', array_slice(
    $processControllerLines,
    $processRelationMethod->getStartLine() - 1,
    $processRelationMethod->getEndLine() - $processRelationMethod->getStartLine() + 1
));
foreach ([
    'assertProcessReadable',
    'historyByProcessInstance',
    'workflowProjectProductItemIds',
    'listForWorkflowProject',
] as $requiredGuard) {
    if (!str_contains($processRelationSource, $requiredGuard)) {
        throw new RuntimeException('workflow project-product endpoint missing guard: ' . $requiredGuard);
    }
}

$relationServiceSource = file_get_contents($root . '/app/service/biz/SaleProjectProductItemRelationService.php');
$relationMethod = new ReflectionMethod(SaleProjectProductItemRelationService::class, 'listForWorkflowProject');
$relationServiceLines = is_string($relationServiceSource)
    ? file($root . '/app/service/biz/SaleProjectProductItemRelationService.php')
    : false;
if (!is_array($relationServiceLines)) {
    throw new RuntimeException('unable to read workflow relation service source');
}
$safeRelationSource = implode('', array_slice(
    $relationServiceLines,
    $relationMethod->getStartLine() - 1,
    $relationMethod->getEndLine() - $relationMethod->getStartLine() + 1
));
foreach (['PURCHASE_PRICE', 'SALE_PRICE', 'MIN_PRICE', 'purchasePrice', 'salePrice', 'minPrice'] as $priceField) {
    if (str_contains($safeRelationSource, $priceField)) {
        throw new RuntimeException('workflow project-product endpoint exposes price field: ' . $priceField);
    }
}

$authMiddlewareSource = file_get_contents($root . '/app/middleware/AuthMiddleware.php');
if (!is_string($authMiddlewareSource)
    || !str_contains($authMiddlewareSource, '/biz/process/project/product-item-relation/list')
) {
    throw new RuntimeException('workflow project-product endpoint must use authenticated bootstrap routing');
}

$productControllerSource = file_get_contents($root . '/app/controller/biz/ProductController.php');
if (!is_string($productControllerSource)
    || !str_contains($productControllerSource, '$this->childrenInput($request),')
    || !str_contains($productControllerSource, '$this->authPayload($request)')
) {
    throw new RuntimeException('product children endpoint must pass the auth payload');
}

foreach (['queryProcess', 'queryProcessList', 'projectRuntimeQueryList'] as $methodName) {
    $method = new ReflectionMethod(WorkflowQueryService::class, $methodName);
    assertSameAuthorizationValue(2, $method->getNumberOfParameters(), $methodName . ' accepts auth payload');
}

echo "workflow project-query authorization smoke passed\n";
