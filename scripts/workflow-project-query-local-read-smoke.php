#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\controller\biz\ProcessController;
use app\service\biz\BizDraftService;
use app\service\biz\CollectionReceiptService;
use app\service\biz\DebitNoteService;
use app\service\biz\ProductService;
use app\service\biz\PurchaseOrderService;
use app\service\biz\ReturnOrderService;
use app\service\biz\SaleProjectService;
use app\service\biz\SaleProjectProductItemRelationService;
use app\service\workflow\WorkflowQueryService;
use think\facade\Db;

$options = getopt('', ['env-file:']);
$envFile = trim((string)($options['env-file'] ?? ''));
if ($envFile === '' || !is_file($envFile)) {
    fwrite(STDERR, "usage: php workflow-project-query-local-read-smoke.php --env-file=/absolute/path\n");
    exit(2);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
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

$app = new think\App($root);
$app->env->load($envFile);
$app->initialize();

function expectDeniedRead(callable $callback, string $name): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if ($exception->getCode() === 403) {
            return;
        }

        throw new RuntimeException($name . ' returned ' . $exception->getCode());
    }

    throw new RuntimeException($name . ' did not deny cross-tenant access');
}

$project = Db::name('biz_sale_project')
    ->where(function ($query): void {
        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->whereNotNull('TENANT_ID')
    ->where('TENANT_ID', '<>', '')
    ->field('ID,TENANT_ID')
    ->order('ID', 'desc')
    ->find();
if (!is_array($project) || $project === []) {
    throw new RuntimeException('local smoke requires one active sale project');
}

$projectId = (string)$project['ID'];
$tenantId = (string)$project['TENANT_ID'];
$payload = ['user_id' => 'read-smoke', 'tenant_id' => $tenantId, 'role_codes' => ['tenantAdmin']];
$wrongPayload = ['user_id' => 'read-smoke', 'tenant_id' => 'tenant-outside-smoke', 'role_codes' => ['tenantAdmin']];
$workflow = new WorkflowQueryService();

$saleProject = new SaleProjectService();
$saleProject->assertReadable($projectId, $payload);
$saleProject->assertScopedReadable($projectId, $payload);
expectDeniedRead(
    static fn (): mixed => $saleProject->assertScopedReadable($projectId, $wrongPayload),
    'sale project auxiliary read'
);

$draft = new BizDraftService($saleProject);
$draft->detail($projectId, $payload);
expectDeniedRead(
    static fn (): mixed => $draft->detail($projectId, $wrongPayload),
    'sale project draft read'
);

$product = Db::name('biz_product')
    ->where('TENANT_ID', $tenantId)
    ->where(function ($query): void {
        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->field('ID')
    ->order('ID', 'desc')
    ->find();
if (is_array($product) && $product !== []) {
    $productId = (string)$product['ID'];
    $products = new ProductService();
    $products->children([$productId], $payload);
    expectDeniedRead(
        static fn (): mixed => $products->children([$productId], $wrongPayload),
        'kit-product children read'
    );
}

$workflowProjectItem = Db::name('biz_sale_project_product_item')
    ->alias('i')
    ->join('biz_sale_project project', 'project.ID = i.PROJECT_ID', 'INNER')
    ->where('i.TENANT_ID', $tenantId)
    ->where('project.TENANT_ID', $tenantId)
    ->where(function ($query): void {
        $query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->where(function ($query): void {
        $query->whereNull('project.DELETE_FLAG')->whereOr('project.DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->field('i.ID,i.PROJECT_ID')
    ->find();
if (!is_array($workflowProjectItem) || $workflowProjectItem === []) {
    throw new RuntimeException('local smoke requires one active sale-project product item');
}
$workflowRelations = new SaleProjectProductItemRelationService();
$workflowRelationRows = $workflowRelations->listForWorkflowProject(
    [(string)$workflowProjectItem['ID']],
    (string)$workflowProjectItem['PROJECT_ID'],
    $payload
);
foreach ($workflowRelationRows as $workflowRelationRow) {
    foreach (['purchasePrice', 'salePrice', 'minPrice'] as $priceField) {
        if (array_key_exists($priceField, $workflowRelationRow)) {
            throw new RuntimeException('workflow relation read exposed ' . $priceField);
        }
    }
}
expectDeniedRead(
    static fn (): mixed => $workflowRelations->listForWorkflowProject(
        [(string)$workflowProjectItem['ID']],
        (string)$workflowProjectItem['PROJECT_ID'],
        $wrongPayload
    ),
    'workflow project-product relation cross-tenant read'
);
expectDeniedRead(
    static fn (): mixed => $workflowRelations->listForWorkflowProject(
        [(string)$workflowProjectItem['ID']],
        'project-outside-process-smoke',
        $payload
    ),
    'workflow project-product relation cross-project read'
);

$purchaseOrder = Db::name('biz_purchase_order')
    ->where('TENANT_ID', $tenantId)
    ->where(function ($query): void {
        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })
    ->field('ID')
    ->order('ID', 'desc')
    ->find();
if (is_array($purchaseOrder) && $purchaseOrder !== []) {
    $purchaseOrderId = (string)$purchaseOrder['ID'];
    $purchaseOrders = new PurchaseOrderService();
    $purchaseOrders->assertReadable($purchaseOrderId, $payload);
    expectDeniedRead(
        static fn (): mixed => $purchaseOrders->assertReadable($purchaseOrderId, $wrongPayload),
        'purchase-order process-list read'
    );

    $processRows = $workflow->queryProcessList([
        'processKeyList' => ['Process_procure_in_warehouse', 'Process_reimbursement', 'Process_make_payment'],
        'attribute' => ['objectId' => $purchaseOrderId],
    ], $payload);
    foreach ($processRows as $processRow) {
        if (array_key_exists('variable', $processRow) || array_key_exists('amount', $processRow)) {
            throw new RuntimeException('purchase-order process list exposed workflow variables');
        }
    }
}

$scopeRows = [
    [
        'name' => 'purchase order',
        'service' => new PurchaseOrderService(),
        'row' => Db::name('biz_purchase_order')
            ->where('TENANT_ID', $tenantId)
            ->where('ORG', '<>', '')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->field('ID,TENANT_ID,ORG')
            ->find(),
    ],
    [
        'name' => 'debit note',
        'service' => new DebitNoteService(),
        'row' => Db::name('biz_debit_note')
            ->where('TENANT_ID', $tenantId)
            ->where('ORG', '<>', '')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->field('ID,TENANT_ID,ORG')
            ->find(),
    ],
    [
        'name' => 'collection receipt',
        'service' => new CollectionReceiptService(),
        'row' => Db::name('biz_collection_receipt')
            ->alias('c')
            ->join('biz_payment_record p', 'p.ID = c.PAYMENT_RECORD_ID', 'INNER')
            ->where('c.TENANT_ID', $tenantId)
            ->where('p.ORG', '<>', '')
            ->where(function ($query): void {
                $query->whereNull('c.DELETE_FLAG')->whereOr('c.DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->field('c.ID,c.TENANT_ID,p.ORG AS ORG')
            ->find(),
    ],
    [
        'name' => 'return order',
        'service' => new ReturnOrderService(),
        'row' => Db::name('return_order')
            ->where('TENANT_ID', $tenantId)
            ->where('ORG', '<>', '')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
            })
            ->field('ID,TENANT_ID,ORG')
            ->find(),
    ],
];
$processController = new ProcessController();
$objectVariableGuard = new ReflectionMethod(ProcessController::class, 'assertObjectVariableReadable');
$coveredScopeCases = [];
foreach ($scopeRows as $case) {
    $row = $case['row'];
    if (!is_array($row) || $row === []) {
        continue;
    }

    $objectId = trim((string)($row['ID'] ?? ''));
    $objectTenantId = trim((string)($row['TENANT_ID'] ?? ''));
    $objectOrgId = trim((string)($row['ORG'] ?? ''));
    $scopePayload = [
        'user_id' => 'scope-read-smoke',
        'tenant_id' => $objectTenantId,
        'data_scope_org_ids' => [$objectOrgId],
    ];
    $outsideScopePayload = [
        'user_id' => 'outside-scope-read-smoke',
        'tenant_id' => $objectTenantId,
        'data_scope_org_ids' => ['org-outside-scope-smoke'],
    ];
    $emptyScopePayload = [
        'user_id' => 'outside-scope-read-smoke',
        'tenant_id' => $objectTenantId,
        'data_scope_org_ids' => [],
    ];

    $case['service']->assertReadable($objectId, $scopePayload);
    expectDeniedRead(
        static fn (): mixed => $case['service']->assertReadable($objectId, $outsideScopePayload),
        $case['name'] . ' same-tenant outside-scope read'
    );
    expectDeniedRead(
        static fn (): mixed => $case['service']->assertReadable($objectId, $emptyScopePayload),
        $case['name'] . ' same-tenant empty-scope read'
    );
    $objectVariableGuard->invoke($processController, [
        'variableName' => 'objectId',
        'variable' => $objectId,
    ], $scopePayload);
    expectDeniedRead(
        static fn (): mixed => $objectVariableGuard->invoke($processController, [
            'variableName' => 'objectId',
            'variable' => $objectId,
        ], $outsideScopePayload),
        $case['name'] . ' workflow lookup outside data scope'
    );
    $coveredScopeCases[] = $case['name'];
}
if (count($coveredScopeCases) !== count($scopeRows)) {
    throw new RuntimeException(
        'object scope local smoke missing rows for: '
        . implode(', ', array_diff(array_column($scopeRows, 'name'), $coveredScopeCases))
    );
}

$runtimeProjectId = Db::name('act_ru_variable')
    ->alias('v')
    ->join('act_ru_execution e', 'e.PROC_INST_ID_ = v.PROC_INST_ID_', 'INNER')
    ->where('v.NAME_', 'projectId')
    ->where('e.TENANT_ID_', $tenantId)
    ->whereNotNull('v.TEXT_')
    ->where('v.TEXT_', '<>', '')
    ->value('v.TEXT_');
if (is_string($runtimeProjectId) && trim($runtimeProjectId) !== '') {
    $sameTenantRows = $workflow->projectRuntimeQueryList(['projectId' => $runtimeProjectId], $payload);
    $otherTenantRows = $workflow->projectRuntimeQueryList(['projectId' => $runtimeProjectId], $wrongPayload);
    if ($sameTenantRows === [] || $otherTenantRows !== []) {
        throw new RuntimeException('workflow runtime tenant filter failed');
    }
}

echo "workflow project-query local read smoke passed\n";
