#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\SaleProjectService;
use app\service\biz\SaleProjectBillingService;
use app\service\biz\ReturnOrderService;
use app\service\biz\AfterSalesService;
use app\service\biz\ProductService;
use app\service\workflow\WorkflowRuntimeService;
use app\support\FileDownloadUrl;
use app\support\LegacyFileSource;
use app\support\TenantScope;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message . ': expected an exception');
}

assertSameValue(
    '/backend/dev/file/download?id=123',
    FileDownloadUrl::normalizeLegacy('https://oa.xzx8.com/backend//dev/file/download?id=123'),
    'legacy absolute file URL'
);
assertSameValue(
    '/backend/dev/file/download?id=abc',
    FileDownloadUrl::normalizeLegacy('http://47.95.5.233:7971/dev/file/download?id=abc'),
    'legacy Java file URL'
);
assertSameValue(
    'https://example.com/manual.pdf',
    FileDownloadUrl::normalizeLegacy('https://example.com/manual.pdf'),
    'unrelated external URL'
);
assertSameValue(
    'oa.xzx8.com',
    LegacyFileSource::host('https://oa.xzx8.com/backend/dev/file/download?id={id}'),
    'legacy file source host'
);
assertSameValue(
    'https://oa.xzx8.com/backend/dev/file/download?id=file%2F123',
    LegacyFileSource::urlFor('https://oa.xzx8.com/backend/dev/file/download?id={id}', 'file/123'),
    'legacy file source URL encoding'
);
assertThrows(
    static fn(): string => LegacyFileSource::validateDownloadUrlTemplate(
        'https://user:password@oa.xzx8.com/backend/dev/file/download?id={id}'
    ),
    'legacy file source credentials'
);
assertThrows(
    static fn(): string => LegacyFileSource::validateDownloadUrlTemplate(
        'https://oa.xzx8.com/backend/dev/file/download'
    ),
    'legacy file source placeholder'
);

$tenantPayload = ['tenant_id' => '1', 'role_codes' => ['tenantAdmin']];
$superPayload = ['tenant_id' => '1', 'role_codes' => ['superAdmin']];
assertSameValue(['tenantId' => '1'], TenantScope::scopedFilters(['tenantId' => '2'], $tenantPayload), 'tenant filter override');
assertSameValue(['tenantId' => '2'], TenantScope::scopedFilters(['tenantId' => '2'], $superPayload), 'super admin global filter');
assertSameValue(false, TenantScope::canCrossTenant($tenantPayload), 'tenant admin cross-tenant access');
assertSameValue(true, TenantScope::canCrossTenant($superPayload), 'super admin cross-tenant access');

$costMethod = new ReflectionMethod(SaleProjectService::class, 'addProductCostAmount');
$costService = new SaleProjectService();
$projectFields = (new ReflectionClass(SaleProjectService::class))->getConstant('PROJECT_FIELDS');
assertSameValue(
    true,
    is_string($projectFields)
        && str_contains($projectFields, 'p.TRAVEL_DAYS AS TRAVEL_DAYS')
        && str_contains($projectFields, 'AFTER_SALES_TRAVEL_USED_DAYS'),
    'sale project fields expose planned and cumulative after-sales travel days'
);
$travelDaysMethod = new ReflectionMethod(SaleProjectService::class, 'workflowTravelDays');
assertSameValue('0.0', $travelDaysMethod->invoke($costService, 0, 'travelDays', true), 'zero means no project travel');
assertSameValue('1.5', $travelDaysMethod->invoke($costService, 1.5, 'travelDays', false), 'half-day project travel');
assertThrows(
    static fn(): mixed => $travelDaysMethod->invoke($costService, 1.3, 'travelDays', true),
    'project travel days reject non-half-day values'
);
$productItemRowsMethod = new ReflectionMethod(SaleProjectService::class, 'productItemRows');
$productItemRows = $productItemRowsMethod->invoke($costService, [[
    'ID' => 'item-1',
    'PROJECT_ID' => 'project-1',
    'PRODUCT_ID' => 'product-1',
    'NUMBER' => '1',
    'DELIVERY' => '0',
    'SPECS' => 'cover',
    'PURCHASE_PRICE' => '12.5',
]]);
assertSameValue('cover', $productItemRows[0]['productSpecs'] ?? null, 'sale project product specification alias');
assertSameValue(0, $productItemRows[0]['delivery'] ?? null, 'sale project delivered quantity zero');
$shipmentSummaryMethod = new ReflectionMethod(SaleProjectService::class, 'summarizePendingShipmentItems');
assertSameValue(
    [
        'project-1' => [
            'pendingNormalQuantity' => 2,
            'pendingReissueQuantity' => 3,
            'pendingReissueOrderCount' => 1,
            'hasPendingNormalShipment' => true,
            'hasPendingReissue' => true,
        ],
    ],
    $shipmentSummaryMethod->invoke($costService, [
        ['PROJECT_ID' => 'project-1', 'CATEGORY' => 'INIT', 'NUMBER' => 3, 'DELIVERY' => 1],
        [
            'PROJECT_ID' => 'project-1',
            'CATEGORY' => 'REISSUE_ORDER',
            'NUMBER' => 5,
            'DELIVERY' => 2,
            'PROJECT_REISSUE_ORDER_ID' => 'reissue-1',
        ],
    ]),
    'pending shipment summary separates normal and reissue quantities'
);
$shipmentExistsSqlMethod = new ReflectionMethod(SaleProjectService::class, 'pendingShipmentExistsSql');
assertSameValue(
    true,
    str_contains($shipmentExistsSqlMethod->invoke($costService, 'REISSUE_ORDER'), "shipment_item.CATEGORY = 'REISSUE_ORDER'"),
    'pending reissue shipment query category'
);
$reissueSummaryMethod = new ReflectionMethod(SaleProjectBillingService::class, 'reissueShipmentSummary');
$billingService = new SaleProjectBillingService();
assertSameValue(
    [
        'shipmentStatus' => 'PARTIALLY_REISSUED',
        'totalQuantity' => 5,
        'deliveredQuantity' => 2,
        'pendingQuantity' => 3,
    ],
    $reissueSummaryMethod->invoke($billingService, [
        ['number' => 2, 'delivery' => 2],
        ['number' => 3, 'delivery' => 0],
    ]),
    'reissue shipment progress summary'
);
$invoiceShipmentSummaryMethod = new ReflectionMethod(SaleProjectBillingService::class, 'invoiceShipmentSummary');
assertSameValue(
    [
        'shipmentType' => 'MIXED',
        'hasReissueShipment' => true,
        'reissueOrders' => [[
            'id' => 'reissue-1',
            'createTime' => '2026-07-11 08:39:16',
            'createUser' => 'user-1',
            'createUserName' => 'Warehouse User',
            'remark' => 'replacement',
        ]],
    ],
    $invoiceShipmentSummaryMethod->invoke($billingService, [
        ['projectProductItemCategory' => 'INIT'],
        [
            'projectProductItemCategory' => 'REISSUE_ORDER',
            'projectReissueOrderId' => 'reissue-1',
            'reissueOrderCreateTime' => '2026-07-11 08:39:16',
            'reissueOrderCreateUser' => 'user-1',
            'reissueOrderCreateUserName' => 'Warehouse User',
            'reissueOrderRemark' => 'replacement',
        ],
    ]),
    'delivery invoice summary links reissue order information'
);
$invoiceItemFieldsMethod = new ReflectionMethod(SaleProjectBillingService::class, 'invoiceItemFields');
$invoiceItemFields = $invoiceItemFieldsMethod->invoke($billingService);
assertSameValue(
    true,
    str_contains($invoiceItemFields, 'pi.PROJECT_REISSUE_ORDER_ID AS PROJECT_REISSUE_ORDER_ID')
        && str_contains($invoiceItemFields, 'reissue.CREATE_TIME AS REISSUE_ORDER_CREATE_TIME'),
    'delivery invoice item fields expose reissue relation'
);
$inventoryRequirementsMethod = new ReflectionMethod(SaleProjectService::class, 'inventoryRequirementsForProductItems');
assertSameValue(
    [
        'single' => 2.0,
        'component' => 6.0,
        'accessory' => 3.0,
    ],
    $inventoryRequirementsMethod->invoke($costService, [
        ['productId' => 'single', 'number' => 2, 'children' => []],
        [
            'productId' => 'kit',
            'number' => 3,
            'children' => [
                ['productId' => 'component', 'number' => 2],
                ['productId' => 'accessory', 'number' => 1],
            ],
        ],
    ]),
    'sale project inventory requirements include kit components'
);
$amounts = [];
$names = [];
$single = ['number' => 10, 'productId' => 'single', 'productName' => 'Single'];
$singleArgs = [&$amounts, &$names, $single, -1, 2.0];
$costMethod->invokeArgs($costService, $singleArgs);
assertSameValue(-2.0, $amounts['single'], 'partial single-product return cost');

$amounts = [];
$names = [];
$kit = [
    'number' => 5,
    'children' => [[
        'targetId' => 'component',
        'number' => 3,
        'productName' => 'Component',
    ]],
];
$kitArgs = [&$amounts, &$names, $kit, -1, 2.0];
$costMethod->invokeArgs($costService, $kitArgs);
assertSameValue(-6.0, $amounts['component'], 'partial kit return cost');

$returnOrderService = new ReturnOrderService();
$returnBusinessStateMethod = new ReflectionMethod(ReturnOrderService::class, 'returnOrderBusinessState');
assertSameValue(
    'WAIT_WAREHOUSE_RECEIPT',
    $returnBusinessStateMethod->invoke($returnOrderService, 'WAIT_RECEIVE', 'NOT_READY'),
    'return order waits for warehouse receipt before finance'
);
assertSameValue(
    'WAIT_FINANCE_REFUND',
    $returnBusinessStateMethod->invoke($returnOrderService, 'RECEIVED', 'WAIT_REFUND'),
    'received return order enters finance refund state'
);
assertSameValue(
    'COMPLETED',
    $returnBusinessStateMethod->invoke($returnOrderService, 'RECEIVED', 'NOT_REQUIRED'),
    'received no-refund return order completes without finance'
);
$returnOptionsMethod = new ReflectionMethod(ReturnOrderService::class, 'returnOptionsFromInput');
$legacyReturnOptionsMethod = new ReflectionMethod(ReturnOrderService::class, 'returnOrderOptions');
$legacyReturnOptions = $legacyReturnOptionsMethod->invoke($returnOrderService, null);
assertSameValue(true, $legacyReturnOptions['refundRequired'], 'legacy return order still requires refund');
$noRefundOptions = $returnOptionsMethod->invoke(
    $returnOrderService,
    ['refundRequired' => false, 'treasurer' => 'finance-user'],
    json_encode(['source' => 'regression', 'treasurer' => 'legacy-finance'])
);
assertSameValue(false, $noRefundOptions['refundRequired'], 'no-refund option is persisted');
assertSameValue('', $noRefundOptions['treasurer'], 'no-refund option clears finance approver');
$noRefundExtJson = json_decode($noRefundOptions['extJson'], true);
assertSameValue('regression', $noRefundExtJson['source'] ?? null, 'return option preserves existing extJson');
assertSameValue(false, $noRefundExtJson['refundRequired'] ?? null, 'return extJson stores no-refund choice');
$refundOptions = $returnOptionsMethod->invoke(
    $returnOrderService,
    ['refundRequired' => true, 'treasurer' => ['id' => 'finance-user']],
    null
);
assertSameValue('finance-user', $refundOptions['treasurer'], 'refund option stores selected finance approver');
$returnStateMethod = new ReflectionMethod(ReturnOrderService::class, 'stateForReturnRequirement');
assertSameValue(
    'AlreadySettled',
    $returnStateMethod->invoke($returnOrderService, '125.35', false),
    'no-refund return order is not selectable for finance payment'
);
assertSameValue(
    'Unsettled',
    $returnStateMethod->invoke($returnOrderService, '125.35', true),
    'refund-required return order remains payable'
);
$workflowRuntimeSource = file_get_contents(dirname(__DIR__) . '/app/service/workflow/WorkflowRuntimeService.php');
$reissueStart = strpos($workflowRuntimeSource, 'public function startProjectReissueProcess');
$returnStart = strpos($workflowRuntimeSource, 'public function startProjectReturnProcess');
$initialProcessStart = strpos($workflowRuntimeSource, 'private function startInitialApprovalProcess');
assertSameValue(true, is_int($reissueStart) && is_int($returnStart) && is_int($initialProcessStart), 'workflow source sections exist');
$reissueSource = substr($workflowRuntimeSource, $reissueStart, $returnStart - $reissueStart);
$returnSource = substr($workflowRuntimeSource, $returnStart, $initialProcessStart - $returnStart);
assertSameValue(false, str_contains($reissueSource, 'refundRequired'), 'reissue flow is isolated from return refund choice');
assertSameValue(true, str_contains($returnSource, "missing refundRequired"), 'return flow requires refund choice');
assertSameValue(true, str_contains($returnSource, "'treasurer'"), 'return flow stores conditional finance approver');
$calculatedReturnAmountMethod = new ReflectionMethod(ReturnOrderService::class, 'calculatedReturnAmount');
assertSameValue(
    '125.35',
    $calculatedReturnAmountMethod->invoke($returnOrderService, [
        ['returnAmountCents' => 10000],
        ['returnAmountCents' => 2535],
    ]),
    'return amount is the authoritative sum of returned product values'
);

$afterSalesService = new AfterSalesService();
$sanitizeAfterSalesHtml = new ReflectionMethod(AfterSalesService::class, 'sanitizeHtml');
$sanitizedAfterSalesHtml = $sanitizeAfterSalesHtml->invoke(
    $afterSalesService,
    '<p onclick="alert(1)">处理完成</p><script>alert(2)</script><img src=&#106;avascript:alert(3)><a href="javascript:alert(4)">详情</a>'
);
assertSameValue(false, str_contains(strtolower($sanitizedAfterSalesHtml), '<script'), 'after-sales HTML strips script tags');
assertSameValue(false, str_contains(strtolower($sanitizedAfterSalesHtml), 'onclick='), 'after-sales HTML strips event handlers');
assertSameValue(false, str_contains(strtolower($sanitizedAfterSalesHtml), 'javascript:'), 'after-sales HTML strips javascript URLs');
$afterSalesSummary = new ReflectionMethod(AfterSalesService::class, 'summary');
assertSameValue('处理完成', $afterSalesSummary->invoke($afterSalesService, '<p>处理完成</p>'), 'after-sales content summary');
$productService = new ProductService();
$attachInventoryDistribution = new ReflectionMethod(ProductService::class, 'attachInventoryDistribution');
$productsWithInventory = $attachInventoryDistribution->invoke(
    $productService,
    [['id' => 'product-1', 'productName' => '测试产品'], ['id' => 'product-2', 'productName' => '无库存产品']],
    [
        'product-1' => [
            ['warehouseId' => 'warehouse-1', 'warehouseName' => '一号仓库', 'currentCount' => 5],
            ['warehouseId' => 'warehouse-2', 'warehouseName' => '二号仓库', 'currentCount' => -1.5],
        ],
    ]
);
assertSameValue(2, $productsWithInventory[0]['warehouseCount'] ?? null, 'product warehouse distribution count');
assertSameValue(3.5, $productsWithInventory[0]['totalInventory'] ?? null, 'product warehouse total inventory');
assertSameValue([], $productsWithInventory[1]['warehouseInventory'] ?? null, 'product without warehouse inventory');
$afterSalesInstaller = file_get_contents(dirname(__DIR__) . '/scripts/install-after-sales-module.php');
assertSameValue(
    true,
    is_string($afterSalesInstaller)
        && str_contains($afterSalesInstaller, 'COLLATE=utf8mb4_general_ci')
        && str_contains($afterSalesInstaller, "'tableCollations'")
        && str_contains($afterSalesInstaller, "'ICON' => null")
        && str_contains($afterSalesInstaller, "'menuIconCleared'")
        && !str_contains($afterSalesInstaller, 'FileTextOutlined')
        && !str_contains($afterSalesInstaller, 'COLLATE=utf8mb4_unicode_ci'),
    'after-sales installer matches production table collation and keeps the submenu icon-free'
);

$deliveryMethod = new ReflectionMethod(WorkflowRuntimeService::class, 'projectDeliveryProcessInstanceId');
$workflowService = new WorkflowRuntimeService();
$projectInitInputMethod = new ReflectionMethod(WorkflowRuntimeService::class, 'assertProjectInitInput');
$projectInitInputMethod->invoke($workflowService, [
    'accountId' => 'account-1',
    'payerCategory' => 'PAY_FIRST',
    'initPrice' => 100,
    'rebateAmount' => 0,
    'travelDays' => 0,
    'isInvoicing' => false,
]);
assertThrows(
    static fn(): mixed => $projectInitInputMethod->invoke($workflowService, [
        'accountId' => 'account-1',
        'payerCategory' => 'PAY_FIRST',
        'initPrice' => 100,
        'rebateAmount' => 0,
        'isInvoicing' => false,
    ]),
    'project init requires travel days'
);
$travelInstaller = file_get_contents(dirname(__DIR__) . '/scripts/install-sale-project-travel-days.php');
assertSameValue(
    true,
    is_string($travelInstaller)
        && str_contains($travelInstaller, 'ADD COLUMN `TRAVEL_DAYS`')
        && str_contains($travelInstaller, 'idx_leave_after_sales_travel'),
    'project travel installer contains required column and statistics index'
);
$requestId = '1d25a5df-3864-48fc-97b4-2c80f63a80a7';
$expectedProcessId = 'delivery_' . hash('sha256', '1|user-1|project-1|' . $requestId);
$actualProcessId = $deliveryMethod->invoke($workflowService, $requestId, '1', 'user-1', 'project-1');
assertSameValue($expectedProcessId, $actualProcessId, 'delivery request idempotency key');
assertSameValue(73, strlen($actualProcessId), 'delivery process id length');

fwrite(STDOUT, "regression safety smoke passed\n");
