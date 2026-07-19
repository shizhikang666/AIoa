#!/usr/bin/env php
<?php

declare(strict_types=1);

// Keep the database short enough for MySQL's 64-byte GET_LOCK name limit in the repair script.
const SMOKE_DATABASE_PREFIX = 'cprsmk_';
const SMOKE_DATABASE_HOST = '127.0.0.1';
const SMOKE_DATABASE_PORT = 3306;
const SMOKE_DATABASE_USER = 'root';
const MIGRATED_DEMO_TENANT = '2018244380532912130';
const REPAIR_CONFIRMATION = 'repair-demo-permissions-20260719';

/** @return never */
function fail(string $message): void
{
    throw new RuntimeException($message);
}

function quote_identifier(string $identifier): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
        fail('unsafe database identifier');
    }

    return '`' . $identifier . '`';
}

/** @return array{exitCode: int, stdout: string, stderr: string} */
function run_process(array $command, string $workingDirectory, ?array $environment = null): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        $command,
        $descriptors,
        $pipes,
        $workingDirectory,
        $environment,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        fail('unable to start subprocess');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exitCode' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

/** @return array<string, mixed> */
function decode_successful_json(array $result, string $step): array
{
    if (($result['exitCode'] ?? 1) !== 0) {
        fail($step . ' failed: ' . trim((string)($result['stderr'] ?? '')));
    }

    try {
        $decoded = json_decode((string)($result['stdout'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $prefix = substr(trim((string)($result['stdout'] ?? '')), 0, 500);
        fail(
            $step . ' returned invalid JSON (exit '
            . (string)($result['exitCode'] ?? 'unknown')
            . ', stderr=' . substr(trim((string)($result['stderr'] ?? '')), 0, 500)
            . '): ' . $prefix
        );
    }
    if (!is_array($decoded)) {
        fail($step . ' did not return a JSON object');
    }

    return $decoded;
}

/** @return array<string, string> */
function repair_environment(string $database, string $password): array
{
    $environment = getenv();
    $environment = is_array($environment) ? array_map('strval', $environment) : [];

    return array_merge($environment, [
        // ThinkPHP's env() helper reads process overrides from PHP_* variables.
        'PHP_APP_DEBUG' => 'false',
        'PHP_DB_DRIVER' => 'mysql',
        'PHP_DB_TYPE' => 'mysql',
        'PHP_DB_HOST' => SMOKE_DATABASE_HOST,
        'PHP_DB_PORT' => (string)SMOKE_DATABASE_PORT,
        'PHP_DB_NAME' => $database,
        'PHP_DB_USER' => SMOKE_DATABASE_USER,
        'PHP_DB_PASS' => $password,
        'PHP_DB_CHARSET' => 'utf8mb4',
        'PHP_DB_PREFIX' => '',
        'PHP_CACHE_DRIVER' => 'file',
    ]);
}

/** @return array<string, mixed> */
function run_repair(
    string $repairScript,
    string $projectRoot,
    string $database,
    string $password,
    array $arguments
): array {
    $result = run_process(
        array_merge([PHP_BINARY, $repairScript], $arguments),
        $projectRoot,
        repair_environment($database, $password)
    );

    return decode_successful_json($result, 'permission repair');
}

/** @return array<int, array<string, mixed>> */
function relation_snapshot(PDO $pdo, string $database): array
{
    $statement = $pdo->query(
        'SELECT `ID`,`OBJECT_ID`,`TARGET_ID`,`CATEGORY`,`EXT_JSON` FROM '
        . quote_identifier($database)
        . '.`sys_relation` ORDER BY `OBJECT_ID`,`TARGET_ID`,`ID`'
    );
    if ($statement === false) {
        fail('unable to read relation snapshot');
    }

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function remove_tree(string $directory, string $allowedRoot): void
{
    if (!file_exists($directory)) {
        return;
    }

    $normalizedDirectory = str_replace('\\', '/', $directory);
    $normalizedRoot = rtrim(str_replace('\\', '/', $allowedRoot), '/') . '/';
    if (!str_starts_with($normalizedDirectory . '/', $normalizedRoot)) {
        fail('refusing to remove a path outside the smoke temporary root');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            if (!rmdir($entry->getPathname())) {
                fail('failed to remove smoke directory');
            }
        } elseif (!unlink($entry->getPathname())) {
            fail('failed to remove smoke file');
        }
    }
    if (!rmdir($directory)) {
        fail('failed to remove smoke root directory');
    }
}

/** @param array<string, mixed> $profiles */
function seed_fixture(PDO $pdo, string $database, array $profiles): void
{
    $schema = quote_identifier($database);
    $pdo->exec(
        "CREATE TABLE {$schema}.`sys_role` ("
        . '`ID` varchar(64) NOT NULL,`NAME` varchar(128) NULL,'
        . '`CODE` varchar(128) NULL,`ORG_ID` varchar(64) NULL,'
        . '`TENANT_ID` varchar(64) NOT NULL,`DELETE_FLAG` varchar(32) NULL,'
        . 'PRIMARY KEY (`ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        "CREATE TABLE {$schema}.`sys_resource` ("
        . '`ID` varchar(64) NOT NULL,`PATH` varchar(512) NULL,'
        . '`CODE` varchar(255) NULL,`CATEGORY` varchar(64) NULL,'
        . '`DELETE_FLAG` varchar(32) NULL,'
        . 'PRIMARY KEY (`ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        "CREATE TABLE {$schema}.`sys_relation` ("
        . '`ID` varchar(64) NOT NULL,`OBJECT_ID` varchar(512) NOT NULL,'
        . '`TARGET_ID` varchar(512) NOT NULL,`CATEGORY` varchar(128) NOT NULL,'
        . '`EXT_JSON` longtext NULL,'
        . 'PRIMARY KEY (`ID`),'
        . 'KEY `idx_relation_object_category` (`OBJECT_ID`,`CATEGORY`),'
        . 'KEY `idx_relation_target` (`TARGET_ID`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $insertRole = $pdo->prepare(
        "INSERT INTO {$schema}.`sys_role` (`ID`,`NAME`,`CODE`,`ORG_ID`,`TENANT_ID`,`DELETE_FLAG`)"
        . ' VALUES (:id,:name,:code,:orgId,:tenantId,:deleteFlag)'
    );
    $insertResource = $pdo->prepare(
        "INSERT INTO {$schema}.`sys_resource` (`ID`,`PATH`,`CODE`,`CATEGORY`,`DELETE_FLAG`)"
        . ' VALUES (:id,:path,:code,:category,:deleteFlag)'
    );
    $insertRelation = $pdo->prepare(
        "INSERT INTO {$schema}.`sys_relation` (`ID`,`OBJECT_ID`,`TARGET_ID`,`CATEGORY`,`EXT_JSON`)"
        . ' VALUES (:id,:objectId,:targetId,:category,:extJson)'
    );

    $resourceIds = [];
    $relationSequence = 0;
    foreach ($profiles as $profileKey => $profile) {
        if (!is_array($profile)) {
            fail('invalid permission profile fixture');
        }
        $roleId = trim((string)($profile['roleId'] ?? ''));
        if ($roleId === '') {
            fail('profile is missing roleId: ' . $profileKey);
        }
        $insertRole->execute([
            'id' => $roleId,
            'name' => 'Smoke ' . $profileKey,
            'code' => 'smoke_' . $profileKey,
            'orgId' => 'smoke_org_' . $profileKey,
            'tenantId' => MIGRATED_DEMO_TENANT,
            'deleteFlag' => 'NOT_DELETE',
        ]);

        $buttonIds = [];
        foreach (($profile['requiredButtons'] ?? []) as $buttonCode) {
            $buttonCode = trim((string)$buttonCode);
            if ($buttonCode === '') {
                fail('empty required button code');
            }
            $buttonId = 'button_' . substr(hash('sha256', $buttonCode), 0, 24);
            if (!isset($resourceIds['button:' . $buttonCode])) {
                $insertResource->execute([
                    'id' => $buttonId,
                    'path' => null,
                    'code' => $buttonCode,
                    'category' => 'BUTTON',
                    'deleteFlag' => 'NOT_DELETE',
                ]);
                $resourceIds['button:' . $buttonCode] = $buttonId;
            }
            $buttonIds[] = $resourceIds['button:' . $buttonCode];
        }

        $requiredResources = array_values($profile['requiredResources'] ?? []);
        foreach ($requiredResources as $resourceIndex => $path) {
            $path = strtolower(trim((string)$path));
            if ($path === '' || !str_starts_with($path, '/biz/')) {
                fail('invalid required resource path');
            }
            if (!isset($resourceIds['path:' . $path])) {
                $resourceId = 'resource_' . substr(hash('sha256', $path), 0, 24);
                $insertResource->execute([
                    'id' => $resourceId,
                    'path' => $path,
                    'code' => null,
                    'category' => 'MENU',
                    'deleteFlag' => 'NOT_DELETE',
                ]);
                $resourceIds['path:' . $path] = $resourceId;
            }

            $relationSequence++;
            $insertRelation->execute([
                'id' => 'fixture_resource_' . str_pad((string)$relationSequence, 6, '0', STR_PAD_LEFT),
                'objectId' => $roleId,
                'targetId' => $resourceIds['path:' . $path],
                'category' => 'SYS_ROLE_HAS_RESOURCE',
                'extJson' => json_encode(
                    ['buttonInfo' => $resourceIndex === 0 ? $buttonIds : []],
                    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ),
            ]);
        }
    }

    $scopeSources = [
        'sales_supervisor' => [
            '/biz/saleproject/page',
            '/biz/bizproduct/page',
            '/biz/settlementaccount/page',
            '/biz/user/orgtreeselector',
            '/biz/user/userselector',
        ],
        'executive_assistant' => [
            '/biz/user/orgtreeselector',
            '/biz/user/userselector',
        ],
        'finance' => [
            '/biz/user/orgtreeselector',
            '/biz/user/userselector',
        ],
        'procurement' => [
            '/biz/saleproject/page',
            '/biz/user/orgtreeselector',
            '/biz/user/userselector',
        ],
    ];
    foreach ($scopeSources as $profileKey => $apiUrls) {
        $profile = $profiles[$profileKey] ?? null;
        $roleId = is_array($profile) ? trim((string)($profile['roleId'] ?? '')) : '';
        if ($roleId === '') {
            fail('scope-source profile is missing: ' . $profileKey);
        }
        foreach ($apiUrls as $apiUrl) {
            $relationSequence++;
            $insertRelation->execute([
                'id' => 'fixture_permission_' . str_pad((string)$relationSequence, 6, '0', STR_PAD_LEFT),
                'objectId' => $roleId,
                'targetId' => $apiUrl,
                'category' => 'SYS_ROLE_HAS_PERMISSION',
                'extJson' => json_encode([
                    'apiUrl' => $apiUrl,
                    'scopeCategory' => 'SCOPE_ORG_CHILD',
                    'scopeDefineOrgIdList' => [],
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
    }
}

function main(): void
{
    $options = getopt('', ['password-env::']);
    $passwordEnvironment = trim((string)($options['password-env'] ?? 'OA_PERMISSION_SMOKE_DB_PASSWORD'));
    if ($passwordEnvironment === '' || preg_match('/^[A-Z][A-Z0-9_]*$/', $passwordEnvironment) !== 1) {
        fail('--password-env must name an uppercase environment variable');
    }
    $password = getenv($passwordEnvironment);
    if (!is_string($password) || $password === '') {
        fail('set ' . $passwordEnvironment . ' to the local MySQL root password');
    }

    $projectRoot = dirname(__DIR__);
    $repairScript = __DIR__ . DIRECTORY_SEPARATOR . 'repair-migrated-demo-role-permissions.php';
    if (!is_file($repairScript) || !is_file($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
        fail('run this smoke from a complete project checkout');
    }

    $database = SMOKE_DATABASE_PREFIX . bin2hex(random_bytes(6));
    if (
        !str_starts_with($database, SMOKE_DATABASE_PREFIX)
        || str_starts_with(strtolower($database), 'oa2026')
        || preg_match('/^[a-z0-9_]+$/', $database) !== 1
    ) {
        fail('generated database name failed the isolation policy');
    }

    $temporaryRoot = rtrim(sys_get_temp_dir(), '/\\')
        . DIRECTORY_SEPARATOR . 'oa-permission-integration-smoke-' . bin2hex(random_bytes(8));
    if (!mkdir($temporaryRoot, 0700, true) && !is_dir($temporaryRoot)) {
        fail('unable to create smoke temporary root');
    }

    $pdo = null;
    $databaseCreated = false;
    $primaryFailure = null;
    $successSummary = null;

    try {
        $pdo = new PDO(
            'mysql:host=' . SMOKE_DATABASE_HOST . ';port=' . SMOKE_DATABASE_PORT . ';charset=utf8mb4',
            SMOKE_DATABASE_USER,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
            ]
        );
        // CREATE without IF NOT EXISTS guarantees a collision never reuses or mutates an existing schema.
        $pdo->exec(
            'CREATE DATABASE ' . quote_identifier($database)
            . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );
        $databaseCreated = true;

        $planDump = decode_successful_json(
            run_process([PHP_BINARY, $repairScript, '--dump-plan'], $projectRoot),
            'permission profile dump'
        );
        seed_fixture($pdo, $database, $planDump);

        $initialSnapshot = relation_snapshot($pdo, $database);
        if ($initialSnapshot === []) {
            fail('fixture did not create baseline relations');
        }

        $dryRunArguments = [
            '--tenant-id=' . MIGRATED_DEMO_TENANT,
            '--database=' . $database,
        ];
        $firstDryRun = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            $dryRunArguments
        );
        $secondDryRun = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            $dryRunArguments
        );
        $initialPlanSha256 = trim((string)($firstDryRun['planSha256'] ?? ''));
        $initialStateSha256 = trim((string)($firstDryRun['relationStateSha256'] ?? ''));
        $plannedInsertCount = (int)($firstDryRun['plannedInsertCount'] ?? -1);
        if (
            ($firstDryRun['mode'] ?? null) !== 'dry-run'
            || ($firstDryRun['database'] ?? null) !== $database
            || ($firstDryRun['tenantId'] ?? null) !== MIGRATED_DEMO_TENANT
            || preg_match('/^[a-f0-9]{64}$/', $initialPlanSha256) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $initialStateSha256) !== 1
            || $plannedInsertCount <= 0
        ) {
            fail('first dry-run returned an invalid plan');
        }
        if (
            !hash_equals($initialPlanSha256, (string)($secondDryRun['planSha256'] ?? ''))
            || !hash_equals($initialStateSha256, (string)($secondDryRun['relationStateSha256'] ?? ''))
            || $plannedInsertCount !== (int)($secondDryRun['plannedInsertCount'] ?? -1)
        ) {
            fail('two unchanged dry-runs produced different plans');
        }
        if (relation_snapshot($pdo, $database) !== $initialSnapshot) {
            fail('dry-run mutated permission relations');
        }

        $firstBackupDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . 'first-apply';
        $firstApply = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            array_merge($dryRunArguments, [
                '--apply',
                '--backup-dir=' . $firstBackupDirectory,
                '--confirm=' . REPAIR_CONFIRMATION,
                '--plan-sha256=' . $initialPlanSha256,
                '--expected-insert-count=' . $plannedInsertCount,
            ])
        );
        $insertedIds = $firstApply['insertedIds'] ?? null;
        if (
            ($firstApply['mode'] ?? null) !== 'apply'
            || (int)($firstApply['plannedInsertCount'] ?? -1) !== $plannedInsertCount
            || (int)($firstApply['insertedCount'] ?? -1) !== $plannedInsertCount
            || !is_array($insertedIds)
            || count($insertedIds) !== $plannedInsertCount
            || count(array_unique(array_map('strval', $insertedIds))) !== $plannedInsertCount
        ) {
            fail('first apply did not insert the reviewed plan exactly once');
        }
        $afterFirstApply = relation_snapshot($pdo, $database);
        if (count($afterFirstApply) !== count($initialSnapshot) + $plannedInsertCount) {
            fail('first apply relation count is inconsistent');
        }

        $postApplyDryRun = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            $dryRunArguments
        );
        $idempotentPlanSha256 = trim((string)($postApplyDryRun['planSha256'] ?? ''));
        if (
            (int)($postApplyDryRun['plannedInsertCount'] ?? -1) !== 0
            || preg_match('/^[a-f0-9]{64}$/', $idempotentPlanSha256) !== 1
        ) {
            fail('post-apply dry-run was not idempotent');
        }

        $secondBackupDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . 'idempotent-apply';
        $idempotentApply = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            array_merge($dryRunArguments, [
                '--apply',
                '--backup-dir=' . $secondBackupDirectory,
                '--confirm=' . REPAIR_CONFIRMATION,
                '--plan-sha256=' . $idempotentPlanSha256,
                '--expected-insert-count=0',
            ])
        );
        if (
            (int)($idempotentApply['plannedInsertCount'] ?? -1) !== 0
            || (int)($idempotentApply['insertedCount'] ?? -1) !== 0
            || ($idempotentApply['insertedIds'] ?? null) !== []
            || relation_snapshot($pdo, $database) !== $afterFirstApply
        ) {
            fail('second reviewed apply was not a no-op');
        }

        $rollbackPath = $firstBackupDirectory . DIRECTORY_SEPARATOR . 'rollback-inserted.sql';
        $rollbackSql = is_file($rollbackPath) ? file_get_contents($rollbackPath) : false;
        if (
            !is_string($rollbackSql)
            || !str_contains($rollbackSql, 'USE ' . quote_identifier($database) . ';')
            || !str_contains($rollbackSql, 'DELETE FROM `sys_relation`')
        ) {
            fail('first apply did not produce a usable rollback script');
        }
        $pdo->exec($rollbackSql);
        if (relation_snapshot($pdo, $database) !== $initialSnapshot) {
            fail('generated rollback did not restore the initial relation snapshot');
        }

        $afterRollbackDryRun = run_repair(
            $repairScript,
            $projectRoot,
            $database,
            $password,
            $dryRunArguments
        );
        if (
            !hash_equals($initialPlanSha256, (string)($afterRollbackDryRun['planSha256'] ?? ''))
            || !hash_equals($initialStateSha256, (string)($afterRollbackDryRun['relationStateSha256'] ?? ''))
            || $plannedInsertCount !== (int)($afterRollbackDryRun['plannedInsertCount'] ?? -1)
        ) {
            fail('rollback did not restore the original reviewed plan');
        }

        $successSummary = [
            'databaseWasIsolatedAndDropped' => $database,
            'initialRelationCount' => count($initialSnapshot),
            'plannedInsertCount' => $plannedInsertCount,
            'twoDryRunsMatched' => true,
            'firstApplyInsertedPlan' => true,
            'secondReviewedApplyWasNoOp' => true,
            'rollbackRestoredInitialSnapshot' => true,
        ];
    } catch (Throwable $exception) {
        $primaryFailure = $exception;
    } finally {
        $cleanupFailure = null;
        try {
            if ($databaseCreated && $pdo instanceof PDO) {
                if (!str_starts_with($database, SMOKE_DATABASE_PREFIX) || str_starts_with(strtolower($database), 'oa2026')) {
                    fail('refusing to drop a database outside the smoke namespace');
                }
                $pdo->exec('DROP DATABASE ' . quote_identifier($database));
            }
            remove_tree($temporaryRoot, sys_get_temp_dir());
        } catch (Throwable $exception) {
            $cleanupFailure = $exception;
        }

        if ($cleanupFailure !== null) {
            throw new RuntimeException('smoke cleanup failed: ' . $cleanupFailure->getMessage(), 0, $primaryFailure);
        }
    }

    if ($primaryFailure !== null) {
        throw $primaryFailure;
    }
    if (!is_array($successSummary)) {
        fail('smoke did not reach its success state');
    }

    echo json_encode(
        $successSummary,
        JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ), PHP_EOL;
}

try {
    main();
} catch (Throwable $exception) {
    fwrite(STDERR, 'permission repair integration smoke failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
