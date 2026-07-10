#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\SaleProjectService;
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

$deliveryMethod = new ReflectionMethod(WorkflowRuntimeService::class, 'projectDeliveryProcessInstanceId');
$workflowService = new WorkflowRuntimeService();
$requestId = '1d25a5df-3864-48fc-97b4-2c80f63a80a7';
$expectedProcessId = 'delivery_' . hash('sha256', '1|user-1|project-1|' . $requestId);
$actualProcessId = $deliveryMethod->invoke($workflowService, $requestId, '1', 'user-1', 'project-1');
assertSameValue($expectedProcessId, $actualProcessId, 'delivery request idempotency key');
assertSameValue(73, strlen($actualProcessId), 'delivery process id length');

fwrite(STDOUT, "regression safety smoke passed\n");
