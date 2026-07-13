<?php

declare(strict_types=1);

use think\facade\Db;

$appRoot = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : getcwd();
require $appRoot . '/vendor/autoload.php';

$app = new think\App();
$app->initialize();

$apply = in_array('--apply', $argv, true);
$backupDir = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backup-dir=')) {
        $backupDir = trim(substr($arg, strlen('--backup-dir=')));
    }
}

if ($apply && ($backupDir === null || $backupDir === '')) {
    fwrite(STDERR, "Refusing --apply without --backup-dir=/absolute/path\n");
    exit(2);
}

$tenantId = '2018244380532912130';
$rootOrgId = '2018244380591632386';
$operatorId = '1543837863788879870';

$commonApiPrefixes = [
    '/sys/sysConfig/detail',
    '/sys/index/message/list',
    '/dev/dict/tree',
    '/dev/message/createSseConnect',
    '/biz/user/orgTreeSelector',
];

$rolePlans = [
    'demoAdmin' => [
        'roleName' => '总经理',
        'roleOrgId' => $rootOrgId,
        'accounts' => ['superAdminTwo'],
        'menuPathPrefixes' => ['/biz', '/sys/org', '/sys/user', '/sys/position', '/sys/role'],
        'apiPrefixes' => ['/biz/', '/sys/org/', '/sys/user/', '/sys/position/', '/sys/role/', '/sys/userCenter/'],
    ],
    'hr' => [
        'roleName' => '行政人事',
        'roleOrgId' => '1781507827814435873',
        'accounts' => ['cszjb001'],
        'menuPathPrefixes' => ['/biz/org', '/biz/user', '/biz/position', '/biz/bizpayroll', '/biz/bizleaveapplication', '/biz/biztask'],
        'apiPrefixes' => ['/biz/org/', '/biz/user/', '/biz/position/', '/biz/bizpayroll/', '/biz/bizleaveapplication/', '/biz/task/', '/biz/process/', '/sys/userCenter/'],
    ],
    'sales' => [
        'roleName' => '销售总监',
        'roleOrgId' => '1781507845340705631',
        'accounts' => ['csyw001'],
        'menuPathPrefixes' => ['/biz/customer', '/biz/saleproject', '/biz/biztask', '/biz/historytask', '/biz/copytask'],
        'apiPrefixes' => ['/biz/customer/', '/biz/customerfollowup/', '/biz/saleproject/', '/biz/saleprojectfollowup/', '/biz/task/', '/biz/ccrecords/page', '/biz/ccrecords/detail', '/biz/process/', '/sys/userCenter/'],
    ],
    'finance' => [
        'roleName' => '财务经理',
        'roleOrgId' => '1781507858382802232',
        'accounts' => ['cscw001'],
        'menuPathPrefixes' => ['/biz/settlementaccount', '/biz/bizcollectionreceipt', '/biz/bizdebitnote', '/biz/paymentrecord', '/biz/bizexpenditurerecord', '/biz/bizpayroll', '/biz/bizdatareport', '/biz/biztask'],
        'apiPrefixes' => ['/biz/settlementaccount/', '/biz/settlementaccountpayment/', '/biz/bizcollectionreceipt/', '/biz/bizdebitnote/', '/biz/bizpaymentrecord/', '/biz/bizexpenditurerecord/', '/biz/bizpayroll/', '/biz/bizdatareport/', '/biz/task/', '/biz/process/', '/sys/userCenter/'],
    ],
    'tech' => [
        'roleName' => 'PHP工程师',
        'roleOrgId' => '1781507923771398086',
        'accounts' => ['csjs001'],
        'menuPathPrefixes' => ['/biz/biztask', '/biz/historytask', '/biz/saleproject', '/biz/saleprojectproductinfo', '/biz/bizproduct', '/biz/inventory'],
        'apiPrefixes' => ['/biz/task/', '/biz/process/', '/biz/saleproject/', '/biz/saleprojectproductinfo/', '/biz/saleprojectproductitem/', '/biz/saleprojectproductitemrelation/', '/biz/bizproduct/', '/biz/inventory/', '/biz/warehouses/list', '/biz/bizdatareport/saleProjectList/details', '/sys/userCenter/'],
    ],
];

foreach ($rolePlans as &$rolePlan) {
    $rolePlan['apiPrefixes'] = array_values(array_unique(array_merge($rolePlan['apiPrefixes'], $commonApiPrefixes)));
}
unset($rolePlan);

function new_id(): string
{
    static $lastMs = 0;
    static $sequence = 0;

    $ms = (int)floor(microtime(true) * 1000);
    if ($ms === $lastMs) {
        $sequence++;
    } else {
        $lastMs = $ms;
        $sequence = random_int(100, 999);
    }

    return (string)$ms . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT);
}

function route_api_urls(array $prefixes): array
{
    global $appRoot;
    $routeFile = $appRoot . '/route/app.php';
    $contents = is_file($routeFile) ? file_get_contents($routeFile) : '';
    if (!is_string($contents) || $contents === '') {
        return [];
    }

    $urls = [];
    if (preg_match_all("/Route::group\\('([^']+)'\\s*,\\s*function\\s*\\(\\)\\s*\\{(.*?)\\}\\)->middleware/s", $contents, $groups, PREG_SET_ORDER)) {
        foreach ($groups as $group) {
            $groupPath = '/' . trim($group[1], '/');
            if (preg_match_all("/Route::(?:get|post|put|delete|patch)\\('([^']+)'/", $group[2], $routes)) {
                foreach ($routes[1] as $route) {
                    $urls[] = strtolower($groupPath . '/' . trim($route, '/'));
                }
            }
        }
    }

    if (preg_match_all("/Route::(?:get|post|put|delete|patch)\\('([^']+)'/", $contents, $standalone)) {
        foreach ($standalone[1] as $route) {
            $route = '/' . trim($route, '/');
            $urls[] = strtolower($route);
        }
    }

    $prefixes = array_map(static fn (string $prefix): string => strtolower($prefix), $prefixes);

    return array_values(array_unique(array_filter($urls, static function (string $url) use ($prefixes): bool {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($url, $prefix)) {
                return true;
            }
        }

        return false;
    })));
}

function active_role(string $name, string $orgId, string $tenantId): ?array
{
    $row = Db::name('sys_role')
        ->where('NAME', $name)
        ->where('ORG_ID', $orgId)
        ->where('TENANT_ID', $tenantId)
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
        })
        ->find();

    return is_array($row) && $row !== [] ? $row : null;
}

function active_user(string $account, string $tenantId): ?array
{
    $row = Db::name('sys_user')
        ->where('ACCOUNT', $account)
        ->where('TENANT_ID', $tenantId)
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
        })
        ->find();

    return is_array($row) && $row !== [] ? $row : null;
}

function menu_resources(array $pathPrefixes): array
{
    $pathPrefixes = array_map(static fn (string $prefix): string => strtolower($prefix), $pathPrefixes);
    $rows = Db::name('sys_resource')
        ->whereIn('CATEGORY', ['MODULE', 'MENU'])
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
        })
        ->field('ID,PARENT_ID,TITLE,PATH,CATEGORY,MODULE,SORT_CODE')
        ->select()
        ->toArray();

    $byId = [];
    foreach ($rows as $row) {
        $byId[(string)$row['ID']] = $row;
    }

    $selected = [];
    foreach ($rows as $row) {
        $path = strtolower((string)($row['PATH'] ?? ''));
        foreach ($pathPrefixes as $prefix) {
            if ($path !== '' && str_starts_with($path, $prefix)) {
                $current = (string)$row['ID'];
                while ($current !== '' && isset($byId[$current])) {
                    $selected[$current] = $byId[$current];
                    $parentId = (string)($byId[$current]['PARENT_ID'] ?? '');
                    if ($parentId === '' || $parentId === '0') {
                        $moduleId = (string)($byId[$current]['MODULE'] ?? '');
                        if ($moduleId !== '' && isset($byId[$moduleId])) {
                            $selected[$moduleId] = $byId[$moduleId];
                        }
                        break;
                    }
                    $current = $parentId;
                }
                break;
            }
        }
    }

    uasort($selected, static fn (array $a, array $b): int => ((int)($a['SORT_CODE'] ?? 0)) <=> ((int)($b['SORT_CODE'] ?? 0)));

    return array_values($selected);
}

function button_ids_for_menu_ids(array $menuIds): array
{
    if ($menuIds === []) {
        return [];
    }

    $buttons = Db::name('sys_resource')
        ->where('CATEGORY', 'BUTTON')
        ->whereIn('PARENT_ID', $menuIds)
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
        })
        ->field('ID,PARENT_ID,CODE,TITLE')
        ->select()
        ->toArray();

    $byMenu = [];
    foreach ($buttons as $button) {
        $byMenu[(string)$button['PARENT_ID']][] = (string)$button['ID'];
    }

    return $byMenu;
}

function relation_exists(string $objectId, string $targetId, string $category): bool
{
    return Db::name('sys_relation')
        ->where('OBJECT_ID', $objectId)
        ->where('TARGET_ID', $targetId)
        ->where('CATEGORY', $category)
        ->count() > 0;
}

function unique_rows_by_id(array $rows): array
{
    $unique = [];
    foreach ($rows as $row) {
        if (is_array($row) && isset($row['ID'])) {
            $unique[(string)$row['ID']] = $row;
        }
    }

    return array_values($unique);
}

function snapshot_path(string $backupDir, string $fileName): string
{
    return rtrim($backupDir, "\\/") . DIRECTORY_SEPARATOR . $fileName;
}

function write_json_file(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        throw new RuntimeException('failed to encode json backup');
    }

    file_put_contents($path, $json . PHP_EOL);
}

function sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function write_rollback_sql(string $path, array $insertedIds): void
{
    $lines = [
        '-- Roll back rows inserted by scripts/demo-tenant-permission-init.php',
        '-- Generated at ' . date('Y-m-d H:i:s'),
        'START TRANSACTION;',
    ];

    $relationIds = $insertedIds['sys_relation'] ?? [];
    if ($relationIds !== []) {
        $lines[] = 'DELETE FROM `sys_relation` WHERE `ID` IN (' . implode(',', array_map('sql_quote', $relationIds)) . ');';
    }

    $roleIds = $insertedIds['sys_role'] ?? [];
    if ($roleIds !== []) {
        $lines[] = 'DELETE FROM `sys_role` WHERE `ID` IN (' . implode(',', array_map('sql_quote', $roleIds)) . ');';
    }

    $lines[] = 'COMMIT;';
    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

function backup_snapshot(array $rolePlans, string $tenantId): array
{
    $roles = [];
    $users = [];
    foreach ($rolePlans as $plan) {
        $role = active_role($plan['roleName'], $plan['roleOrgId'], $tenantId);
        if ($role !== null) {
            $roles[] = $role;
        }

        foreach ($plan['accounts'] as $account) {
            $user = active_user($account, $tenantId);
            if ($user !== null) {
                $users[] = $user;
            }
        }
    }

    $roles = unique_rows_by_id($roles);
    $users = unique_rows_by_id($users);
    $roleIds = array_values(array_map(static fn (array $row): string => (string)$row['ID'], $roles));
    $userIds = array_values(array_map(static fn (array $row): string => (string)$row['ID'], $users));
    $allActorIds = array_values(array_unique(array_merge($roleIds, $userIds)));

    $relations = [];
    if ($allActorIds !== []) {
        $relations = array_merge(
            $relations,
            Db::name('sys_relation')->whereIn('OBJECT_ID', $allActorIds)->select()->toArray()
        );
    }
    if ($roleIds !== []) {
        $relations = array_merge(
            $relations,
            Db::name('sys_relation')->whereIn('TARGET_ID', $roleIds)->select()->toArray()
        );
    }
    $relations = unique_rows_by_id($relations);

    $resourceIds = [];
    foreach ($rolePlans as $plan) {
        $menus = menu_resources($plan['menuPathPrefixes']);
        $menuIds = array_values(array_unique(array_map(static fn (array $menu): string => (string)$menu['ID'], $menus)));
        $resourceIds = array_merge($resourceIds, $menuIds);
        foreach (button_ids_for_menu_ids($menuIds) as $buttonIds) {
            $resourceIds = array_merge($resourceIds, $buttonIds);
        }
    }
    $resourceIds = array_values(array_unique(array_filter($resourceIds)));
    $resources = $resourceIds === []
        ? []
        : Db::name('sys_resource')->whereIn('ID', $resourceIds)->select()->toArray();

    return [
        'generatedAt' => date('Y-m-d H:i:s'),
        'tenantId' => $tenantId,
        'roles' => $roles,
        'users' => $users,
        'relations' => $relations,
        'resources' => unique_rows_by_id($resources),
    ];
}

$summary = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'tenantId' => $tenantId,
    'rootOrgId' => $rootOrgId,
    'roles' => [],
    'totals' => [
        'rolesToCreate' => 0,
        'userRoleRelationsToInsert' => 0,
        'resourceRelationsToInsert' => 0,
        'permissionRelationsToInsert' => 0,
    ],
    'insertedIds' => [
        'sys_role' => [],
        'sys_relation' => [],
    ],
];

$runner = static function () use ($rolePlans, $tenantId, $operatorId, $apply, &$summary): void {
    foreach ($rolePlans as $key => $plan) {
        $role = active_role($plan['roleName'], $plan['roleOrgId'], $tenantId);
        $roleWillBeCreated = $role === null;
        if ($roleWillBeCreated) {
            $summary['totals']['rolesToCreate']++;
            $role = [
                'ID' => new_id(),
                'ORG_ID' => $plan['roleOrgId'],
                'NAME' => $plan['roleName'],
                'CODE' => 'demo_' . $key,
                'CATEGORY' => 'ORG',
                'SORT_CODE' => 900,
                'EXT_JSON' => null,
                'DELETE_FLAG' => 'NOT_DELETE',
                'CREATE_TIME' => date('Y-m-d H:i:s'),
                'CREATE_USER' => $operatorId,
                'UPDATE_TIME' => date('Y-m-d H:i:s'),
                'UPDATE_USER' => $operatorId,
                'TENANT_ID' => $tenantId,
            ];
            if ($apply) {
                Db::name('sys_role')->insert($role);
                $summary['insertedIds']['sys_role'][] = (string)$role['ID'];
            }
        }

        $roleId = (string)$role['ID'];
        $menus = menu_resources($plan['menuPathPrefixes']);
        $menuIds = array_values(array_unique(array_map(static fn (array $menu): string => (string)$menu['ID'], $menus)));
        $buttonsByMenu = button_ids_for_menu_ids($menuIds);
        $apiUrls = route_api_urls($plan['apiPrefixes']);

        $roleSummary = [
            'key' => $key,
            'roleId' => $roleId,
            'roleName' => $plan['roleName'],
            'roleOrgId' => $plan['roleOrgId'],
            'roleWillBeCreated' => $roleWillBeCreated,
            'accounts' => [],
            'menuCount' => count($menuIds),
            'menuSample' => array_slice(array_map(static fn (array $m): string => (string)($m['TITLE'] ?? $m['PATH'] ?? $m['ID']), $menus), 0, 12),
            'apiPermissionCount' => count($apiUrls),
            'apiSample' => array_slice($apiUrls, 0, 12),
            'toInsert' => [
                'userRole' => 0,
                'resource' => 0,
                'permission' => 0,
            ],
        ];

        foreach ($plan['accounts'] as $account) {
            $user = active_user($account, $tenantId);
            $userState = ['account' => $account, 'found' => $user !== null, 'userId' => $user['ID'] ?? null, 'willBindRole' => false];
            if ($user !== null && !relation_exists((string)$user['ID'], $roleId, 'SYS_USER_HAS_ROLE')) {
                $userState['willBindRole'] = true;
                $roleSummary['toInsert']['userRole']++;
                $summary['totals']['userRoleRelationsToInsert']++;
                if ($apply) {
                    $relationId = new_id();
                    Db::name('sys_relation')->insert([
                        'ID' => $relationId,
                        'OBJECT_ID' => (string)$user['ID'],
                        'TARGET_ID' => $roleId,
                        'CATEGORY' => 'SYS_USER_HAS_ROLE',
                        'EXT_JSON' => null,
                    ]);
                    $summary['insertedIds']['sys_relation'][] = $relationId;
                }
            }
            $roleSummary['accounts'][] = $userState;
        }

        foreach ($menuIds as $menuId) {
            if (!relation_exists($roleId, $menuId, 'SYS_ROLE_HAS_RESOURCE')) {
                $roleSummary['toInsert']['resource']++;
                $summary['totals']['resourceRelationsToInsert']++;
                if ($apply) {
                    $relationId = new_id();
                    Db::name('sys_relation')->insert([
                        'ID' => $relationId,
                        'OBJECT_ID' => $roleId,
                        'TARGET_ID' => $menuId,
                        'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE',
                        'EXT_JSON' => json_encode(['buttonInfo' => $buttonsByMenu[$menuId] ?? []], JSON_UNESCAPED_UNICODE),
                    ]);
                    $summary['insertedIds']['sys_relation'][] = $relationId;
                }
            }
        }

        foreach ($apiUrls as $apiUrl) {
            if (!relation_exists($roleId, $apiUrl, 'SYS_ROLE_HAS_PERMISSION')) {
                $roleSummary['toInsert']['permission']++;
                $summary['totals']['permissionRelationsToInsert']++;
                if ($apply) {
                    $relationId = new_id();
                    Db::name('sys_relation')->insert([
                        'ID' => $relationId,
                        'OBJECT_ID' => $roleId,
                        'TARGET_ID' => $apiUrl,
                        'CATEGORY' => 'SYS_ROLE_HAS_PERMISSION',
                        'EXT_JSON' => json_encode([
                            'scopeCategory' => 'SCOPE_ORG_CHILD',
                            'scopeDefineOrgIdList' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                    $summary['insertedIds']['sys_relation'][] = $relationId;
                }
            }
        }

        $summary['roles'][] = $roleSummary;
    }
};

$backupFiles = [];
if ($backupDir !== null && $backupDir !== '') {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        throw new RuntimeException('failed to create backup dir: ' . $backupDir);
    }

    $beforePath = snapshot_path($backupDir, 'before-snapshot.json');
    write_json_file($beforePath, backup_snapshot($rolePlans, $tenantId));
    $backupFiles['beforeSnapshot'] = $beforePath;
}

if ($apply) {
    Db::transaction($runner);
} else {
    $runner();
}

$summary['backupFiles'] = $backupFiles;
if ($backupDir !== null && $backupDir !== '') {
    $summaryPath = snapshot_path($backupDir, 'apply-summary.json');
    $rollbackPath = snapshot_path($backupDir, 'rollback-inserted.sql');
    $summary['backupFiles']['applySummary'] = $summaryPath;
    $summary['backupFiles']['rollbackSql'] = $rollbackPath;
    write_json_file($summaryPath, $summary);
    write_rollback_sql($rollbackPath, $summary['insertedIds']);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
