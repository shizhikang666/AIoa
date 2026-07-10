#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\SaleProjectService;
use app\service\workflow\WorkflowRuntimeService;
use app\support\FileDownloadUrl;
use app\support\TenantScope;

require dirname(__DIR__) . '/vendor/autoload.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
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

$tenantPayload = ['tenant_id' => '1', 'role_codes' => ['tenantAdmin']];
$superPayload = ['tenant_id' => '1', 'role_codes' => ['superAdmin']];
assertSameValue(['tenantId' => '1'], TenantScope::scopedFilters(['tenantId' => '2'], $tenantPayload), 'tenant filter override');
assertSameValue(['tenantId' => '2'], TenantScope::scopedFilters(['tenantId' => '2'], $superPayload), 'super admin global filter');
assertSameValue(false, TenantScope::canCrossTenant($tenantPayload), 'tenant admin cross-tenant access');
assertSameValue(true, TenantScope::canCrossTenant($superPayload), 'super admin cross-tenant access');

$costMethod = new ReflectionMethod(SaleProjectService::class, 'addProductCostAmount');
$costService = new SaleProjectService();
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

$deliveryMethod = new ReflectionMethod(WorkflowRuntimeService::class, 'projectDeliveryProcessInstanceId');
$workflowService = new WorkflowRuntimeService();
$requestId = '1d25a5df-3864-48fc-97b4-2c80f63a80a7';
$expectedProcessId = 'delivery_' . hash('sha256', '1|user-1|project-1|' . $requestId);
$actualProcessId = $deliveryMethod->invoke($workflowService, $requestId, '1', 'user-1', 'project-1');
assertSameValue($expectedProcessId, $actualProcessId, 'delivery request idempotency key');
assertSameValue(73, strlen($actualProcessId), 'delivery process id length');

fwrite(STDOUT, "regression safety smoke passed\n");
