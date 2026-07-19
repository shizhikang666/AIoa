#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\ReturnOrderService;
use app\service\biz\SaleProjectService;
use app\service\workflow\WorkflowQueryService;
use think\facade\Db;

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

$options = getopt('', ['env-file:']);
$envFile = trim((string)($options['env-file'] ?? ''));
if ($envFile !== '') {
    if (!is_file($envFile)) {
        throw new RuntimeException('env file not found: ' . $envFile);
    }
    $app = new think\App($root);
    $app->env->load($envFile);
    $app->initialize();
}

function assertScopeSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

function assertScopeDenied(callable $callback, string $message): void
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

$workflow = new WorkflowQueryService();
$allProcessScope = new ReflectionMethod(WorkflowQueryService::class, 'allProcessReadScope');
$allProcessScope->setAccessible(true);

$ordinaryPayload = [
    'user_id' => 'user-1',
    'tenant_id' => 'tenant-1',
    'permission_codes' => ['/biz/process/all/page'],
    'data_scopes' => [],
];
assertScopeSame(
    [
        'allowAll' => false,
        'orgIds' => [],
        'userId' => 'user-1',
        'tenantId' => 'tenant-1',
    ],
    $allProcessScope->invoke($workflow, $ordinaryPayload),
    'missing exact all-process data scope fails closed to participant scope'
);

$unrelatedScopePayload = $ordinaryPayload;
$unrelatedScopePayload['data_scopes'] = [[
    'apiUrl' => '/biz/saleproject/page',
    'scopeCategory' => 'SCOPE_ALL',
    'scopeOrgIdList' => ['org-unrelated'],
]];
assertScopeSame(
    [],
    $allProcessScope->invoke($workflow, $unrelatedScopePayload)['orgIds'],
    'unrelated API data scope does not widen all-process access'
);

$orgScopePayload = $ordinaryPayload;
$orgScopePayload['data_scopes'] = [[
    'apiUrl' => '/biz/process/all/page',
    'scopeCategory' => 'SCOPE_ORG_CHILD',
    'scopeOrgIdList' => ['org-1', 'org-2', 'org-1'],
]];
assertScopeSame(
    ['org-1', 'org-2'],
    $allProcessScope->invoke($workflow, $orgScopePayload)['orgIds'],
    'all-process access uses only its exact resolved org scope'
);

$allScopePayload = $ordinaryPayload;
$allScopePayload['data_scopes'] = [[
    'apiUrl' => '/biz/process/all/page',
    'scopeCategory' => 'SCOPE_ALL',
    'scopeOrgIdList' => [],
]];
assertScopeSame(
    true,
    $allProcessScope->invoke($workflow, $allScopePayload)['allowAll'],
    'explicit all-process SCOPE_ALL retains manager-wide visibility'
);
assertScopeSame(
    true,
    $allProcessScope->invoke($workflow, [
        'user_id' => 'tenant-admin',
        'tenant_id' => 'tenant-1',
        'role_codes' => ['tenantAdmin'],
    ])['allowAll'],
    'tenant administrator retains the all-process page'
);
assertScopeDenied(
    static fn (): mixed => $allProcessScope->invoke($workflow, ['user_id' => 'user-1']),
    'all-process scope requires a concrete tenant'
);

$summaryRows = new ReflectionMethod(WorkflowQueryService::class, 'processSummaryRows');
$summaryRows->setAccessible(true);
$summary = $summaryRows->invoke($workflow, [[
    'id' => 'process-1',
    'title' => 'display title',
    'remark' => 'display remark',
    'amount' => '12.50',
    'variable' => [
        'amount' => '12.50',
        'privateForm' => 'must-not-leak',
        'token' => 'must-not-leak',
    ],
]], true)[0];
assertScopeSame(
    ['amount' => '12.50'],
    $summary['variable'],
    'all-process list returns only the display amount variable'
);
if (array_key_exists('privateForm', $summary['variable']) || array_key_exists('token', $summary['variable'])) {
    throw new RuntimeException('all-process list leaked full workflow variables');
}

$denyingProjectGuard = new class extends SaleProjectService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
        throw new RuntimeException('permission denied', 403);
    }
};
$returnOrders = new ReturnOrderService($denyingProjectGuard);
assertScopeDenied(
    static fn (): mixed => $returnOrders->query(['projectId' => 'project-1'], $ordinaryPayload),
    'return-order project query cannot bypass project visibility'
);
assertScopeSame(
    [['id' => 'project-1', 'payload' => $ordinaryPayload]],
    $denyingProjectGuard->calls,
    'return-order query delegates to the sale-project visibility guard'
);

$returnSource = file_get_contents(dirname(__DIR__) . '/app/service/biz/ReturnOrderService.php');
if (!is_string($returnSource)) {
    throw new RuntimeException('unable to read ReturnOrderService source');
}
$applyScope = new ReflectionMethod(ReturnOrderService::class, 'applyDataScope');
$returnLines = file(dirname(__DIR__) . '/app/service/biz/ReturnOrderService.php');
if (!is_array($returnLines)) {
    throw new RuntimeException('unable to read ReturnOrderService lines');
}
$applyScopeSource = implode('', array_slice(
    $returnLines,
    $applyScope->getStartLine() - 1,
    $applyScope->getEndLine() - $applyScope->getStartLine() + 1
));
foreach (['$requestedOrgIds', 'array_intersect($requestedOrgIds, $scope)', "\$payload['user_id']"] as $guard) {
    if (!str_contains($applyScopeSource, $guard)) {
        throw new RuntimeException('return-order org filter missing scope intersection guard: ' . $guard);
    }
}

$workflowSource = file_get_contents(dirname(__DIR__) . '/app/service/workflow/WorkflowQueryService.php');
if (!is_string($workflowSource)
    || !str_contains($workflowSource, 'authorizedAllProcessQuery')
    || !str_contains($workflowSource, 'applyHistoricalProcessParticipantScope')
    || !str_contains($workflowSource, 'processSummaryRows($this->historicProcessRows(')
) {
    throw new RuntimeException('all-process list is missing scoped summary enforcement');
}

if ($envFile !== '') {
    $tenantId = trim((string)Db::name('sys_user')
        ->whereNotNull('TENANT_ID')
        ->where('TENANT_ID', '<>', '')
        ->order('ID', 'asc')
        ->value('TENANT_ID'));
    if ($tenantId === '') {
        throw new RuntimeException('local scope smoke requires one tenant user');
    }

    $adminPage = $workflow->allProcessPage(['current' => 1, 'size' => 2], [
        'user_id' => 'scope-smoke-admin',
        'tenant_id' => $tenantId,
        'role_codes' => ['tenantAdmin'],
    ]);
    foreach ($adminPage['records'] as $record) {
        assertScopeSame(
            ['amount'],
            array_keys((array)($record['variable'] ?? [])),
            'database-backed all-process page returns a summary variable only'
        );
    }

    // This executes every participant EXISTS branch and proves that a stale
    // permission payload does not fall back to an unrestricted tenant query.
    $workflow->allProcessPage(['current' => 1, 'size' => 2], [
        'user_id' => 'scope-smoke-unrelated-user',
        'tenant_id' => $tenantId,
        'permission_codes' => ['/biz/process/all/page'],
        'data_scopes' => [],
    ]);

    $orgId = trim((string)Db::name('sys_org')
        ->where('TENANT_ID', $tenantId)
        ->order('ID', 'asc')
        ->value('ID'));
    if ($orgId !== '') {
        $workflow->allProcessPage(['current' => 1, 'size' => 2], [
            'user_id' => 'scope-smoke-manager',
            'tenant_id' => $tenantId,
            'permission_codes' => ['/biz/process/all/page'],
            'data_scopes' => [[
                'apiUrl' => '/biz/process/all/page',
                'scopeCategory' => 'SCOPE_ORG',
                'scopeOrgIdList' => [$orgId],
            ]],
        ]);

        (new ReturnOrderService())->page(['orgId' => $orgId, 'current' => 1, 'size' => 2], [
            'user_id' => 'scope-smoke-manager',
            'tenant_id' => $tenantId,
            'data_scope_org_ids' => [$orgId],
        ]);
    }
}

echo "workflow and return-query scope authorization smoke passed\n";
