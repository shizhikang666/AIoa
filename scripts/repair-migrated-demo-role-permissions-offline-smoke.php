#!/usr/bin/env php
<?php

declare(strict_types=1);

$script = __DIR__ . DIRECTORY_SEPARATOR . 'repair-migrated-demo-role-permissions.php';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open([PHP_BINARY, $script, '--dump-plan'], $descriptors, $pipes, dirname(__DIR__), null, ['bypass_shell' => true]);
if (!is_resource($process)) {
    throw new RuntimeException('unable to inspect permission plan');
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    throw new RuntimeException('permission plan dump failed: ' . trim((string)$stderr));
}

$plan = json_decode((string)$stdout, true, 512, JSON_THROW_ON_ERROR);
$expectedProfiles = ['sales_supervisor', 'executive_assistant', 'finance', 'procurement'];
if (array_keys($plan) !== $expectedProfiles) {
    throw new RuntimeException('permission repair profile set changed');
}

$expectedRoleIds = [
    'sales_supervisor' => '1784448839395423658',
    'executive_assistant' => '1784448861843395995',
    'finance' => '1784448869686453996',
    'procurement' => '1784449623203688392',
];
$expectedButtons = [
    'sales_supervisor' => ['bizSaleProjectStartProcess'],
    'executive_assistant' => [],
    'finance' => [],
    'procurement' => [],
];
$workflowSelfService = [
    '/biz/ccrecords/page',
    '/biz/ccrecords/detail',
    '/biz/ccrecords/delete',
    '/biz/bizleaveapplication/my/page',
    '/biz/bizuservacation/detail',
    '/biz/user/orgtreeselector',
    '/biz/user/userselector',
];
$projectDetailReads = [
    '/biz/saleproject/detail',
    '/biz/saleprojectreissueorder/list/query',
    '/biz/saleproject/file/relation/list',
    '/biz/process/project/runtime/query/list',
    '/biz/returnorder/query',
    '/biz/customer/detail',
];
$expectedPermissions = [
    'sales_supervisor' => array_merge($workflowSelfService, $projectDetailReads, [
        '/biz/process/query',
        '/biz/bizdraft/detail',
        '/biz/bizdraft/saleproject/add',
        '/biz/settlementaccount/list',
        '/biz/saleprojectinvoicing/customer',
        '/biz/bizproduct/page',
        '/biz/bizproduct/children',
        '/biz/process/project/init/start',
        '/biz/saleproject/page',
        '/biz/saleproject/list/detail',
        '/biz/saleproject/cost/details',
    ]),
    'executive_assistant' => $workflowSelfService,
    'finance' => array_merge($workflowSelfService, ['/biz/process/query']),
    'procurement' => array_merge($workflowSelfService, $projectDetailReads, [
        '/biz/process/query',
        '/biz/process/query/list',
        '/biz/saleproject/page',
    ]),
];
$expectedResources = [
    'sales_supervisor' => [
        '/biz/copytask',
        '/biz/biztask/processlist',
        '/biz/saleproject',
        '/biz/saleproject/dealprojectlist',
        '/biz/saleproject/cancelprojectlist',
    ],
    'executive_assistant' => ['/biz/copytask', '/biz/biztask/processlist'],
    'finance' => [
        '/biz/copytask',
        '/biz/biztask/processlist',
        '/biz/bizcollectionreceipt',
        '/biz/bizdebitnote',
    ],
    'procurement' => [
        '/biz/copytask',
        '/biz/biztask/processlist',
        '/biz/bizpurchaseorder',
        '/biz/saleproject/waitshipment',
        '/biz/saleproject/completeprojectlist',
    ],
];
$forbidden = [
    '/biz/ccrecords/add',
    '/biz/ccrecords/edit',
    '/biz/task/approve',
    '/biz/task/reject',
    '/biz/bizfilerelation/list',
    '/biz/saleproject/delete',
    '/biz/saleproject/repeal',
];

foreach ($plan as $key => $profile) {
    if (($profile['roleId'] ?? null) !== $expectedRoleIds[$key]) {
        throw new RuntimeException('permission repair role id changed: ' . $key);
    }
    if (($profile['requiredButtons'] ?? null) !== $expectedButtons[$key]) {
        throw new RuntimeException('permission repair button contract changed: ' . $key);
    }
    $permissions = $profile['permissions'] ?? [];
    if (!is_array($permissions) || $permissions === [] || count($permissions) !== count(array_unique($permissions))) {
        throw new RuntimeException('invalid or duplicate permission plan: ' . $key);
    }
    foreach ($permissions as $permission) {
        if (!is_string($permission) || !str_starts_with($permission, '/biz/') || strtolower($permission) !== $permission) {
            throw new RuntimeException('permission must be an exact normalized biz route: ' . $key);
        }
    }
    if (array_intersect($permissions, $forbidden) !== []) {
        throw new RuntimeException('permission plan grants an unrelated write route: ' . $key);
    }
    if ($permissions !== $expectedPermissions[$key]) {
        throw new RuntimeException('permission repair exact allow-list changed: ' . $key);
    }
    if (($profile['requiredResources'] ?? null) !== $expectedResources[$key]) {
        throw new RuntimeException('permission repair resource contract changed: ' . $key);
    }
}

$source = file_get_contents($script);
if (!is_string($source)
    || !str_contains($source, 'Refusing --apply without --confirm=')
    || !str_contains($source, '--database must be the exact target database name')
    || !str_contains($source, 'Refusing --apply without --backup-dir=/absolute/path')
    || !str_contains($source, 'Refusing --apply without --plan-sha256')
    || !str_contains($source, 'Refusing --apply without --expected-insert-count')
    || !str_contains($source, 'SELECT GET_LOCK(')
    || !str_contains($source, 'SELECT RELEASE_LOCK(')
    || !str_contains($source, 'Db::transaction')
    || !str_contains($source, 'permission_relation_snapshot(')
    || !str_contains($source, 'relationStateSha256')
    || !str_contains($source, 'permission relation state changed while applying reviewed plan')
    || !str_contains($source, 'rollback-inserted.sql')
    || !str_contains($source, 'resource_button_codes(')
    || !str_contains($source, 'permission_scope_plan(')
    || !str_contains($source, 'assert_existing_permission_scope(')
    || !str_contains($source, 'count($rows) !== 1')
    || !str_contains($source, "'targetId' => 'fixed:SCOPE_SELF'")
    || !str_contains($source, "'targetId' => 'fixed:SCOPE_ORG_CHILD'")
    || !str_contains($source, "permission_relation_scope(\$roleId, '/biz/saleproject/page')")
    || str_contains($source, 'existing_permission_scope_template(')
    || !str_contains($source, "'apiUrl' => \$apiUrl")
    || !str_contains($source, "'EXT_JSON' => \$row['scopeExtJson']")
    || !str_contains($source, 'write_rollback_sql(')
    || str_contains($source, "Db::name('sys_role')->insert")
    || str_contains($source, "Db::name('sys_user')->insert")
) {
    throw new RuntimeException('permission repair safety contract changed');
}

echo "migrated demo role-permission repair offline smoke passed\n";
