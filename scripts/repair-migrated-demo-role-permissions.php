#!/usr/bin/env php
<?php

declare(strict_types=1);

use think\facade\Db;

const MIGRATED_DEMO_TENANT_ID = '2018244380532912130';
const APPLY_CONFIRMATION = 'repair-demo-permissions-20260719';
const RELATION_CATEGORY = 'SYS_ROLE_HAS_PERMISSION';

/**
 * This is an intentionally narrow post-migration repair. It maps only the
 * existing roles in the migrated demo tenant to APIs required by resources
 * those roles already own; it never creates a role, user, menu, or button.
 *
 * @return array<string, array<string, mixed>>
 */
function permission_profiles(): array
{
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

    return [
        'sales_supervisor' => [
            'roleId' => '1784448839395423658',
            'requiredButtons' => ['bizSaleProjectStartProcess'],
            'requiredResources' => [
                '/biz/copytask',
                '/biz/biztask/processlist',
                '/biz/saleproject',
                '/biz/saleproject/dealprojectlist',
                '/biz/saleproject/cancelprojectlist',
            ],
            'permissions' => array_values(array_unique(array_merge(
                $workflowSelfService,
                $projectDetailReads,
                [
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
                ]
            ))),
        ],
        'executive_assistant' => [
            'roleId' => '1784448861843395995',
            'requiredButtons' => [],
            'requiredResources' => [
                '/biz/copytask',
                '/biz/biztask/processlist',
            ],
            'permissions' => $workflowSelfService,
        ],
        'finance' => [
            'roleId' => '1784448869686453996',
            'requiredButtons' => [],
            'requiredResources' => [
                '/biz/copytask',
                '/biz/biztask/processlist',
                '/biz/bizcollectionreceipt',
                '/biz/bizdebitnote',
            ],
            'permissions' => array_values(array_unique(array_merge(
                $workflowSelfService,
                ['/biz/process/query']
            ))),
        ],
        'procurement' => [
            'roleId' => '1784449623203688392',
            'requiredButtons' => [],
            'requiredResources' => [
                '/biz/copytask',
                '/biz/biztask/processlist',
                '/biz/bizpurchaseorder',
                '/biz/saleproject/waitshipment',
                '/biz/saleproject/completeprojectlist',
            ],
            'permissions' => array_values(array_unique(array_merge(
                $workflowSelfService,
                $projectDetailReads,
                [
                    '/biz/process/query',
                    '/biz/process/query/list',
                    '/biz/saleproject/page',
                ]
            ))),
        ],
    ];
}

if (in_array('--dump-plan', $argv, true)) {
    echo json_encode(permission_profiles(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

$appRoot = is_file(__DIR__ . '/../vendor/autoload.php') ? dirname(__DIR__) : getcwd();
require $appRoot . '/vendor/autoload.php';

$app = new think\App($appRoot);
$app->initialize();

$options = getopt('', [
    'apply',
    'tenant-id:',
    'database:',
    'backup-dir:',
    'confirm:',
    'plan-sha256:',
    'expected-insert-count:',
]);
$apply = array_key_exists('apply', $options);
$tenantId = trim((string)($options['tenant-id'] ?? ''));
$expectedDatabase = trim((string)($options['database'] ?? ''));
$backupDir = trim((string)($options['backup-dir'] ?? ''));
$confirmation = trim((string)($options['confirm'] ?? ''));
$confirmedPlanSha256 = strtolower(trim((string)($options['plan-sha256'] ?? '')));
$expectedInsertCount = trim((string)($options['expected-insert-count'] ?? ''));

if ($tenantId !== MIGRATED_DEMO_TENANT_ID) {
    fwrite(STDERR, "Refusing: --tenant-id must identify the migrated demo tenant\n");
    exit(2);
}
if ($expectedDatabase === '' || preg_match('/^[A-Za-z0-9_]+$/', $expectedDatabase) !== 1) {
    fwrite(STDERR, "Refusing: --database must be the exact target database name\n");
    exit(2);
}
if ($apply && $confirmation !== APPLY_CONFIRMATION) {
    fwrite(STDERR, "Refusing --apply without --confirm=" . APPLY_CONFIRMATION . "\n");
    exit(2);
}
if ($apply && ($backupDir === '' || !is_absolute_path($backupDir))) {
    fwrite(STDERR, "Refusing --apply without --backup-dir=/absolute/path\n");
    exit(2);
}
if ($apply && preg_match('/^[a-f0-9]{64}$/', $confirmedPlanSha256) !== 1) {
    fwrite(STDERR, "Refusing --apply without --plan-sha256 from the reviewed dry-run\n");
    exit(2);
}
if ($apply && preg_match('/^(0|[1-9][0-9]*)$/', $expectedInsertCount) !== 1) {
    fwrite(STDERR, "Refusing --apply without --expected-insert-count from the reviewed dry-run\n");
    exit(2);
}

$databaseRow = Db::query('SELECT DATABASE() AS database_name');
$connectedDatabase = trim((string)($databaseRow[0]['database_name'] ?? $databaseRow[0]['DATABASE_NAME'] ?? ''));
if ($connectedDatabase !== $expectedDatabase) {
    throw new RuntimeException('connected database does not match --database');
}
$tableRows = Db::query(
    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '
    . sql_quote($connectedDatabase)
    . " AND TABLE_NAME = 'sys_relation'"
);
$storageEngine = strtoupper(trim((string)($tableRows[0]['ENGINE'] ?? $tableRows[0]['engine'] ?? '')));
if (count($tableRows) !== 1 || $storageEngine !== 'INNODB') {
    throw new RuntimeException('sys_relation must exist and use InnoDB for transactional repair');
}

/** @return array<string, mixed>|null */
function active_role(string $roleId, string $tenantId): ?array
{
    $row = Db::name('sys_role')
        ->where('ID', $roleId)
        ->where('TENANT_ID', $tenantId)
        ->where(function ($query): void {
            $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
        })
        ->field('ID,NAME,CODE,ORG_ID,TENANT_ID,DELETE_FLAG')
        ->find();

    return is_array($row) && $row !== [] ? $row : null;
}

/** @return array<int, string> */
function resource_paths(string $roleId): array
{
    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $path): string => strtolower(trim((string)$path)),
        Db::name('sys_relation')
            ->alias('r')
            ->leftJoin('sys_resource s', 's.ID = r.TARGET_ID')
            ->where('r.OBJECT_ID', $roleId)
            ->where('r.CATEGORY', 'SYS_ROLE_HAS_RESOURCE')
            ->where(function ($query): void {
                $query->whereNull('s.DELETE_FLAG')->whereOr('s.DELETE_FLAG', '<>', 'DELETED');
            })
            ->column('s.PATH')
    ))));
}

/** @return array<int, string> */
function resource_button_codes(string $roleId): array
{
    $buttonIds = [];
    $relationExtJson = Db::name('sys_relation')
        ->where('OBJECT_ID', $roleId)
        ->where('CATEGORY', 'SYS_ROLE_HAS_RESOURCE')
        ->column('EXT_JSON');
    foreach ($relationExtJson as $extJson) {
        if (!is_string($extJson) || trim($extJson) === '') {
            continue;
        }
        $decoded = json_decode($extJson, true);
        if (!is_array($decoded) || !is_array($decoded['buttonInfo'] ?? null)) {
            continue;
        }
        foreach ($decoded['buttonInfo'] as $buttonId) {
            $buttonId = trim((string)$buttonId);
            if ($buttonId !== '') {
                $buttonIds[] = $buttonId;
            }
        }
    }

    $buttonIds = array_values(array_unique($buttonIds));
    if ($buttonIds === []) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $code): string => trim((string)$code),
        Db::name('sys_resource')
            ->whereIn('ID', $buttonIds)
            ->where('CATEGORY', 'BUTTON')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '<>', 'DELETED');
            })
            ->column('CODE')
    ))));
}

/** @return array{targetId: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>} */
function permission_relation_scope(string $roleId, string $sourceApiUrl): array
{
    $rows = Db::name('sys_relation')
        ->where('OBJECT_ID', $roleId)
        ->where('TARGET_ID', $sourceApiUrl)
        ->where('CATEGORY', RELATION_CATEGORY)
        ->order('ID', 'asc')
        ->field('TARGET_ID,EXT_JSON')
        ->select()
        ->toArray();
    if ($rows === []) {
        throw new RuntimeException(
            'role has no exact permission scope source: ' . $roleId . ' ' . $sourceApiUrl
        );
    }
    if (count($rows) !== 1) {
        throw new RuntimeException('ambiguous exact permission scope source: ' . $sourceApiUrl);
    }

    $scopes = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string)($row['EXT_JSON'] ?? ''), true);
        $decoded = is_array($decoded) ? $decoded : [];
        $scopeCategory = trim((string)($decoded['scopeCategory'] ?? $decoded['scope_category'] ?? ''));
        if (!in_array($scopeCategory, [
            'SCOPE_ALL',
            'SCOPE_SELF',
            'SCOPE_ORG',
            'SCOPE_ORG_CHILD',
            'SCOPE_COMPANY_CHILD',
            'SCOPE_ORG_DEFINE',
        ], true)) {
            throw new RuntimeException('invalid exact permission scope source: ' . $sourceApiUrl);
        }
        $definedOrgIds = $decoded['scopeDefineOrgIdList'] ?? $decoded['scope_define_org_id_list'] ?? [];
        if (is_string($definedOrgIds)) {
            $definedOrgIds = explode(',', $definedOrgIds);
        }
        $definedOrgIds = is_array($definedOrgIds) ? array_values(array_unique(array_filter(array_map(
            static fn (mixed $orgId): string => trim((string)$orgId),
            $definedOrgIds
        )))) : [];
        sort($definedOrgIds);
        $scopes[$scopeCategory . ':' . implode(',', $definedOrgIds)] = [
            'targetId' => strtolower(trim((string)($row['TARGET_ID'] ?? ''))),
            'scopeCategory' => $scopeCategory,
            'scopeDefineOrgIdList' => $definedOrgIds,
        ];
    }
    if (count($scopes) !== 1) {
        throw new RuntimeException('ambiguous exact permission scope source: ' . $sourceApiUrl);
    }

    return reset($scopes);
}

/** @return array{targetId: string, scopeCategory: string, scopeDefineOrgIdList: array<int, string>} */
function permission_scope_plan(string $roleId, string $apiUrl): array
{
    $selfScoped = [
        '/biz/ccrecords/page',
        '/biz/ccrecords/detail',
        '/biz/ccrecords/delete',
        '/biz/bizleaveapplication/my/page',
        '/biz/bizuservacation/detail',
        '/biz/process/query',
        '/biz/process/query/list',
    ];
    if (in_array($apiUrl, $selfScoped, true)) {
        return [
            'targetId' => 'fixed:SCOPE_SELF',
            'scopeCategory' => 'SCOPE_SELF',
            'scopeDefineOrgIdList' => [],
        ];
    }

    $orgChildScoped = [
        '/biz/user/orgtreeselector',
        '/biz/user/userselector',
    ];
    if (in_array($apiUrl, $orgChildScoped, true)) {
        return [
            'targetId' => 'fixed:SCOPE_ORG_CHILD',
            'scopeCategory' => 'SCOPE_ORG_CHILD',
            'scopeDefineOrgIdList' => [],
        ];
    }

    $projectScoped = [
        '/biz/saleproject/detail',
        '/biz/saleprojectreissueorder/list/query',
        '/biz/saleproject/file/relation/list',
        '/biz/process/project/runtime/query/list',
        '/biz/returnorder/query',
        '/biz/customer/detail',
        '/biz/bizdraft/detail',
        '/biz/bizdraft/saleproject/add',
        '/biz/process/project/init/start',
        '/biz/saleprojectinvoicing/customer',
        '/biz/saleproject/page',
        '/biz/saleproject/list/detail',
        '/biz/saleproject/cost/details',
    ];
    if (in_array($apiUrl, $projectScoped, true)) {
        return permission_relation_scope($roleId, '/biz/saleproject/page');
    }

    return match ($apiUrl) {
        '/biz/bizproduct/children', '/biz/bizproduct/page'
            => permission_relation_scope($roleId, '/biz/bizproduct/page'),
        '/biz/settlementaccount/list'
            => permission_relation_scope($roleId, '/biz/settlementaccount/page'),
        default => throw new RuntimeException('permission has no reviewed scope policy: ' . $apiUrl),
    };
}

function relation_exists(string $roleId, string $apiUrl): bool
{
    return Db::name('sys_relation')
        ->where('OBJECT_ID', $roleId)
        ->where('TARGET_ID', $apiUrl)
        ->where('CATEGORY', RELATION_CATEGORY)
        ->count() > 0;
}

/**
 * @param array<int, string> $roleIds
 * @return array<int, array<string, mixed>>
 */
function permission_relation_snapshot(array $roleIds, bool $forUpdate = false): array
{
    $query = Db::name('sys_relation')
        ->whereIn('OBJECT_ID', $roleIds)
        ->where('CATEGORY', RELATION_CATEGORY)
        ->field('ID,OBJECT_ID,TARGET_ID,CATEGORY,EXT_JSON')
        ->order(['OBJECT_ID' => 'asc', 'TARGET_ID' => 'asc', 'ID' => 'asc']);
    if ($forUpdate) {
        $query->lock(true);
    }

    return $query->select()->toArray();
}

/** @param array<int, array<string, mixed>> $rows */
function permission_relation_snapshot_sha256(array $rows): string
{
    return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/**
 * Existing permissions are part of the reviewed contract too. Refuse a dry
 * run when a route already has a broader, narrower, duplicate, or otherwise
 * different scope instead of silently reporting that no repair is needed.
 *
 * @param array{scopeCategory: string, scopeDefineOrgIdList: array<int, string>} $expected
 */
function assert_existing_permission_scope(string $roleId, string $apiUrl, array $expected): void
{
    $actual = permission_relation_scope($roleId, $apiUrl);
    $expectedOrgIds = $expected['scopeDefineOrgIdList'];
    sort($expectedOrgIds);
    if (
        !hash_equals($expected['scopeCategory'], $actual['scopeCategory'])
        || $expectedOrgIds !== $actual['scopeDefineOrgIdList']
    ) {
        throw new RuntimeException('existing permission scope differs from reviewed policy: ' . $roleId . ' ' . $apiUrl);
    }
}

function new_relation_id(): string
{
    static $lastMs = 0;
    static $sequence = 0;

    do {
        $ms = (int)floor(microtime(true) * 1000);
        if ($ms === $lastMs) {
            $sequence++;
        } else {
            $lastMs = $ms;
            $sequence = random_int(100, 999);
        }
        $id = (string)$ms . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT);
    } while (Db::name('sys_relation')->where('ID', $id)->count() > 0);

    return $id;
}

function is_absolute_path(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
}

/** @return array<int, string> */
function declared_route_urls(string $routeFile): array
{
    $contents = is_file($routeFile) ? file_get_contents($routeFile) : false;
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('route/app.php is unavailable');
    }

    $urls = [];
    if (preg_match_all("/Route::group\\('([^']+)'\\s*,\\s*function\\s*\\(\\)\\s*\\{(.*?)\\}\\)->middleware/s", $contents, $groups, PREG_SET_ORDER)) {
        foreach ($groups as $group) {
            $prefix = '/' . trim(strtolower($group[1]), '/');
            if (preg_match_all("/Route::(?:get|post|put|delete|patch)\\('([^']+)'/i", $group[2], $routes)) {
                foreach ($routes[1] as $route) {
                    $urls[] = $prefix . '/' . trim(strtolower($route), '/');
                }
            }
        }
    }
    if (preg_match_all("/Route::(?:get|post|put|delete|patch)\\('([^']+)'[^;]*->middleware/i", $contents, $standalone)) {
        foreach ($standalone[1] as $route) {
            $urls[] = '/' . trim(strtolower($route), '/');
        }
    }

    return array_values(array_unique($urls));
}

function ensure_backup_dir(string $backupDir): void
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
        throw new RuntimeException('failed to create backup directory');
    }

    foreach (['before-relations.json', 'apply-summary.json', 'rollback-inserted.sql'] as $file) {
        if (file_exists(rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $file)) {
            throw new RuntimeException('backup directory already contains repair evidence');
        }
    }
}

function write_json(string $path, array $value): void
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) {
        throw new RuntimeException('failed to write JSON evidence');
    }

    write_atomic_file($path, $json . PHP_EOL);
}

function write_atomic_file(string $path, string $contents): void
{
    $temporaryPath = $path . '.tmp-' . bin2hex(random_bytes(8));
    try {
        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException('failed to write evidence file');
        }
        @chmod($temporaryPath, 0600);
        if (!rename($temporaryPath, $path)) {
            throw new RuntimeException('failed to publish evidence file');
        }
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }
}

function sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/** @param array<int, array<string, mixed>> $insertedRows */
function write_rollback_sql(string $path, string $database, array $insertedRows): void
{
    $lines = [
        '-- Roll back only relations inserted by repair-migrated-demo-role-permissions.php',
        '-- Generated at ' . date('c'),
        'USE `' . $database . '`;',
        'START TRANSACTION;',
    ];
    if ($insertedRows !== []) {
        $guards = array_map(static function (array $row): string {
            return '(`ID` = ' . sql_quote((string)$row['id'])
                . ' AND `OBJECT_ID` = ' . sql_quote((string)$row['roleId'])
                . ' AND `TARGET_ID` = ' . sql_quote((string)$row['apiUrl'])
                . ' AND `CATEGORY` = ' . sql_quote(RELATION_CATEGORY)
                . ' AND `EXT_JSON` = ' . sql_quote((string)$row['scopeExtJson']) . ')';
        }, $insertedRows);
        $lines[] = 'DELETE FROM `sys_relation` WHERE ' . implode(PHP_EOL . '   OR ', $guards) . ';';
    }
    $lines[] = 'COMMIT;';

    write_atomic_file($path, implode(PHP_EOL, $lines) . PHP_EOL);
}

$lockName = 'oa-permission-repair-' . $connectedDatabase . '-' . $tenantId;
$lockRows = Db::query('SELECT GET_LOCK(' . sql_quote($lockName) . ', 10) AS acquired');
if ((int)($lockRows[0]['acquired'] ?? $lockRows[0]['ACQUIRED'] ?? 0) !== 1) {
    throw new RuntimeException('another permission repair is already running');
}

try {
    $profiles = permission_profiles();
    $declaredRoutes = declared_route_urls($appRoot . '/route/app.php');
    $roleIds = array_values(array_map(
        static fn (array $profile): string => (string)$profile['roleId'],
        $profiles
    ));
    $beforeRelations = permission_relation_snapshot($roleIds);
    $relationStateSha256 = permission_relation_snapshot_sha256($beforeRelations);
    $preflight = [];
    $planned = [];

    foreach ($profiles as $profileKey => $profile) {
        $roleId = (string)$profile['roleId'];
        $role = active_role($roleId, $tenantId);
        if ($role === null) {
            throw new RuntimeException('expected active role is missing: ' . $profileKey);
        }

        $paths = resource_paths($roleId);
        $missingResources = array_values(array_diff($profile['requiredResources'], $paths));
        if ($missingResources !== []) {
            throw new RuntimeException('role resource contract changed: ' . $profileKey . ' missing ' . implode(',', $missingResources));
        }

        $buttonCodes = resource_button_codes($roleId);
        $missingButtons = array_values(array_diff($profile['requiredButtons'], $buttonCodes));
        if ($missingButtons !== []) {
            throw new RuntimeException('role button contract changed: ' . $profileKey . ' missing ' . implode(',', $missingButtons));
        }
        $permissions = array_values(array_unique(array_map(
            static fn (mixed $url): string => strtolower(trim((string)$url)),
            $profile['permissions']
        )));
        $unknownRoutes = array_values(array_diff($permissions, $declaredRoutes));
        if ($unknownRoutes !== []) {
            throw new RuntimeException('permission plan contains unknown routes: ' . implode(',', $unknownRoutes));
        }

        $missing = [];
        $scopePlans = [];
        foreach ($permissions as $apiUrl) {
            $scopeTemplate = permission_scope_plan($roleId, $apiUrl);
            $scopePlans[$apiUrl] = [
                'scopeSourcePermission' => $scopeTemplate['targetId'],
                'scopeCategory' => $scopeTemplate['scopeCategory'],
                'scopeDefineOrgIdList' => $scopeTemplate['scopeDefineOrgIdList'],
                'existing' => relation_exists($roleId, $apiUrl),
            ];
            if ($scopePlans[$apiUrl]['existing']) {
                assert_existing_permission_scope($roleId, $apiUrl, $scopeTemplate);
                continue;
            }

            $missing[] = $apiUrl;
            $scopeExtJson = json_encode([
                'apiUrl' => $apiUrl,
                'scopeCategory' => $scopeTemplate['scopeCategory'],
                'scopeDefineOrgIdList' => $scopeTemplate['scopeDefineOrgIdList'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $planned[] = [
                'profile' => $profileKey,
                'roleId' => $roleId,
                'apiUrl' => $apiUrl,
                'scopeSourcePermission' => $scopeTemplate['targetId'],
                'scopeCategory' => $scopeTemplate['scopeCategory'],
                'scopeDefineOrgIdList' => $scopeTemplate['scopeDefineOrgIdList'],
                'scopeExtJson' => $scopeExtJson,
            ];
        }

        $preflight[$profileKey] = [
            'role' => $role,
            'requiredResources' => $profile['requiredResources'],
            'requiredButtons' => $profile['requiredButtons'],
            'permissionCount' => count($permissions),
            'missingPermissions' => $missing,
            'scopePlans' => $scopePlans,
        ];
    }

    usort($planned, static fn (array $left, array $right): int => [
        $left['roleId'],
        $left['apiUrl'],
    ] <=> [
        $right['roleId'],
        $right['apiUrl'],
    ]);
    $planJson = json_encode([
        'database' => $connectedDatabase,
        'tenantId' => $tenantId,
        'storageEngine' => $storageEngine,
        'relationStateSha256' => $relationStateSha256,
        'relations' => $planned,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $planSha256 = hash('sha256', $planJson);
    if ($apply && !hash_equals($planSha256, $confirmedPlanSha256)) {
        throw new RuntimeException('permission plan changed since the reviewed dry-run');
    }
    if ($apply && count($planned) !== (int)$expectedInsertCount) {
        throw new RuntimeException('planned insert count changed since the reviewed dry-run');
    }

    if ($apply) {
        foreach ($planned as &$plannedRow) {
            $plannedRow['id'] = new_relation_id();
        }
        unset($plannedRow);
    }

    $backupFiles = [];
    if ($apply) {
        ensure_backup_dir($backupDir);
        $beforePath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'before-relations.json';
        $summaryPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'apply-summary.json';
        $rollbackPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . 'rollback-inserted.sql';
        $backupFiles = [
            'beforeRelations' => $beforePath,
            'applySummary' => $summaryPath,
            'rollbackSql' => $rollbackPath,
        ];
        write_json($beforePath, [
            'generatedAt' => date('c'),
            'database' => $connectedDatabase,
            'tenantId' => $tenantId,
            'planSha256' => $planSha256,
            'relationStateSha256' => $relationStateSha256,
            'plannedRelations' => $planned,
            'profiles' => $preflight,
            'relations' => $beforeRelations,
        ]);
        write_rollback_sql(
            $rollbackPath,
            $connectedDatabase,
            $planned
        );
    }

    $insertedIds = [];
    if ($apply) {
        Db::transaction(static function () use (
            $planned,
            $roleIds,
            $relationStateSha256,
            &$insertedIds
        ): void {
            $lockedRoleIds = array_values(array_map('strval', Db::name('sys_role')
                ->whereIn('ID', $roleIds)
                ->lock(true)
                ->column('ID')));
            sort($lockedRoleIds);
            $expectedRoleIds = $roleIds;
            sort($expectedRoleIds);
            if ($lockedRoleIds !== $expectedRoleIds) {
                throw new RuntimeException('target role set changed while applying reviewed plan');
            }

            $lockedRelations = permission_relation_snapshot($roleIds, true);
            if (!hash_equals(
                $relationStateSha256,
                permission_relation_snapshot_sha256($lockedRelations)
            )) {
                throw new RuntimeException('permission relation state changed while applying reviewed plan');
            }

            foreach ($planned as $row) {
                if (relation_exists($row['roleId'], $row['apiUrl'])) {
                    throw new RuntimeException('permission relation changed while applying reviewed plan');
                }

                Db::name('sys_relation')->insert([
                    'ID' => $row['id'],
                    'OBJECT_ID' => $row['roleId'],
                    'TARGET_ID' => $row['apiUrl'],
                    'CATEGORY' => RELATION_CATEGORY,
                    'EXT_JSON' => $row['scopeExtJson'],
                ]);
                $insertedIds[] = $row['id'];
            }
        });
    }

    $summary = [
        'mode' => $apply ? 'apply' : 'dry-run',
        'database' => $connectedDatabase,
        'tenantId' => $tenantId,
        'storageEngine' => $storageEngine,
        'planSha256' => $planSha256,
        'relationStateSha256' => $relationStateSha256,
        'plannedInsertCount' => count($planned),
        'insertedCount' => count($insertedIds),
        'profiles' => $preflight,
        'insertedIds' => $insertedIds,
        'requiresRelogin' => $apply ? $insertedIds !== [] : $planned !== [],
        'backupFiles' => $backupFiles,
    ];

    if ($apply) {
        write_json($summaryPath, $summary);
    }

    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
} finally {
    Db::query('SELECT RELEASE_LOCK(' . sql_quote($lockName) . ')');
}
