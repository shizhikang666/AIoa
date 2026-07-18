<?php

declare(strict_types=1);

use think\facade\Db;

require __DIR__ . '/lib/isolated-validation-bootstrap.php';

$projectRoot = rtrim((string) getenv('OA_ISOLATED_PROJECT_ROOT'), "/\\");
$runtimePath = rtrim((string) getenv('OA_ISOLATED_RUNTIME_PATH'), "/\\");
if ($projectRoot === '' || $runtimePath === '') {
    http_response_code(500);
    exit('isolated validation environment is incomplete');
}

$documentRoot = $projectRoot . DIRECTORY_SEPARATOR . 'public';
$documentRootReal = realpath($documentRoot);
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$candidate = realpath($documentRoot . DIRECTORY_SEPARATOR . ltrim(
    str_replace('/', DIRECTORY_SEPARATOR, rawurldecode($requestPath)),
    DIRECTORY_SEPARATOR
));
if ($requestPath !== '/'
    && $documentRootReal !== false
    && $candidate !== false
    && str_starts_with(strtolower($candidate), strtolower($documentRootReal . DIRECTORY_SEPARATOR))
    && is_file($candidate)
) {
    return false;
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SCRIPT_FILENAME'] = $documentRoot . DIRECTORY_SEPARATOR . 'index.php';

$app = Oa\IsolatedValidation\boot($projectRoot, $runtimePath);
if ($requestPath === '/__oa_isolated_validation_health') {
    $nonce = trim((string) getenv('OA_ISOLATED_HEALTH_NONCE'));
    $database = trim((string) getenv('OA_ISOLATED_DB_NAME'));
    $rows = Db::query('SELECT DATABASE() AS current_database');
    $actualDatabase = count($rows) === 1 ? trim((string) ($rows[0]['current_database'] ?? '')) : '';
    if (preg_match('/^[a-f0-9]{64}$/', $nonce) !== 1
        || $database === ''
        || $actualDatabase === ''
        || !hash_equals($database, $actualDatabase)
    ) {
        http_response_code(503);
        exit('isolated validation health is unavailable');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'pid' => getmypid(),
        'databaseVerified' => true,
        'proof' => hash_hmac('sha256', $actualDatabase, $nonce),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return;
}
$http = $app->http;
$response = $http->run();
$response->send();
$http->end($response);
