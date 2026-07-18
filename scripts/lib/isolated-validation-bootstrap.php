<?php

declare(strict_types=1);

namespace Oa\IsolatedValidation;

use RuntimeException;
use think\App;
use think\facade\Db;

function boot(string $projectRoot, string $runtimePath): App
{
    $projectRoot = rtrim($projectRoot, "/\\");
    $runtimePath = rtrim($runtimePath, "/\\");
    $database = trim((string) getenv('OA_ISOLATED_DB_NAME'));
    $privateRuntimeRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'runtime');
    $runtimeReal = realpath($runtimePath);
    if ($projectRoot === ''
        || $runtimePath === ''
        || $privateRuntimeRoot === false
        || $runtimeReal === false
        || is_link($runtimePath)
        || !str_starts_with(
            strtolower($runtimeReal . DIRECTORY_SEPARATOR),
            strtolower(rtrim($privateRuntimeRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
        )
        || preg_match('/^oa2026_r[0-9]+_validation_[0-9]{8}_[a-f0-9]{8}$/', $database) !== 1
    ) {
        throw new RuntimeException('isolated validation environment is incomplete');
    }

    $loader = require $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!$loader instanceof \Composer\Autoload\ClassLoader) {
        throw new RuntimeException('composer autoloader is unavailable');
    }
    $loader->setPsr4('app\\', [$projectRoot . DIRECTORY_SEPARATOR . 'app']);

    $app = new App($projectRoot);
    $app->setRuntimePath($runtimeReal . DIRECTORY_SEPARATOR);
    $app->initialize();

    $connections = (array) $app->config->get('database.connections', []);
    $mysql = (array) ($connections['mysql'] ?? []);
    $host = strtolower(trim((string) ($mysql['hostname'] ?? '')));
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('isolated validation refuses a non-loopback database host');
    }
    $mysql['hostname'] = '127.0.0.1';
    $mysql['database'] = $database;
    $connections['mysql'] = $mysql;
    $app->config->set(['connections' => $connections], 'database');
    $dbManager = $app->make(\think\DbManager::class);
    $dbManager->setConfig($app->config);
    $dbManager->connect('mysql', true);

    $prefix = trim((string) getenv('OA_ISOLATED_PREFIX'));
    if (preg_match('/^[a-z0-9_]{8,80}$/', $prefix) !== 1) {
        throw new RuntimeException('isolated validation prefix is invalid');
    }
    $cache = (array) $app->config->get('cache', []);
    $stores = (array) ($cache['stores'] ?? []);
    $file = (array) ($stores['file'] ?? []);
    $file['path'] = $runtimeReal . DIRECTORY_SEPARATOR . 'cache';
    $file['prefix'] = $prefix;
    $stores['file'] = $file;
    $cache['default'] = 'file';
    $cache['stores'] = $stores;
    $app->config->set($cache, 'cache');

    $session = (array) $app->config->get('session', []);
    $session['name'] = strtoupper($prefix) . '_SESSID';
    $session['prefix'] = $prefix;
    $app->config->set($session, 'session');

    return $app;
}

/** @return array{rowCount:int,checksum:string} */
function tableFingerprint(string $table): array
{
    if (preg_match('/^[a-z0-9_]+$/', $table) !== 1) {
        throw new RuntimeException('isolated validation table name is unsafe');
    }
    $rows = Db::query('CHECKSUM TABLE `' . $table . '`');
    if (count($rows) !== 1) {
        throw new RuntimeException('isolated validation table checksum is unavailable');
    }
    $checksum = null;
    foreach ($rows[0] as $column => $value) {
        if (strtolower((string) preg_replace('/[^a-z]/i', '', (string) $column)) === 'checksum') {
            $checksum = $value;
            break;
        }
    }
    if ($checksum === null || $checksum === '') {
        throw new RuntimeException('isolated validation table checksum is unavailable');
    }

    return [
        'rowCount' => Db::name($table)->count(),
        'checksum' => (string) $checksum,
    ];
}

/** @return array<string, array{rowCount:int,checksum:string}> */
function businessFingerprints(): array
{
    $result = [];
    foreach (['biz_purchase_order', 'delivery_record', 'biz_payment_record', 'inventory', 'settlement_account_statement'] as $table) {
        $result[$table] = tableFingerprint($table);
    }

    return $result;
}

/** @param array<string, mixed> $auth */
function authorizationSummary(array $auth): array
{
    $permissionCodes = array_map('strtolower', array_map('strval', (array) ($auth['permission_codes'] ?? [])));
    $roleCodes = array_map('strtolower', array_map('strval', (array) ($auth['role_codes'] ?? [])));

    return [
        'hasApprovePermission' => in_array('/biz/task/approve', $permissionCodes, true),
        'hasPagePermission' => in_array('/biz/task/page', $permissionCodes, true),
        'hasBuiltInRole' => count(array_intersect($roleCodes, ['superadmin', 'tenantadmin'])) > 0,
    ];
}
