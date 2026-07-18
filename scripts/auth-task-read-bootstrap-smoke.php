<?php

declare(strict_types=1);

use app\middleware\AuthMiddleware;
use think\Request;

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/middleware/AuthMiddleware.php';

$middleware = new AuthMiddleware();
$permission = new ReflectionMethod(AuthMiddleware::class, 'hasRoutePermission');
$permission->setAccessible(true);

$payload = [
    'role_codes' => [],
    'permission_codes' => [],
];

$request = static function (string $path, string $method = 'GET'): Request {
    return (new Request())->withServer([
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'PATH_INFO' => $path,
        'SCRIPT_NAME' => '/index.php',
    ]);
};

foreach ([
    '/biz/task/count',
    '/biz/task/list',
    '/biz/task/page',
    '/biz/task/history/page',
    '/biz/task/runtime/activity/detail',
] as $path) {
    if ($permission->invoke($middleware, $request($path), $payload) !== true) {
        throw new RuntimeException("authenticated task read path was denied: {$path}");
    }
}

if ($permission->invoke($middleware, $request('/biz/task/approve', 'POST'), $payload) !== false) {
    throw new RuntimeException('task approval write path must still require explicit permission');
}

if ($permission->invoke($middleware, $request('/biz/task/reject', 'POST'), $payload) !== false) {
    throw new RuntimeException('task rejection write path must still require explicit permission');
}

if ($permission->invoke($middleware, $request('/biz/task/sse/stream'), $payload) !== false) {
    throw new RuntimeException('task SSE path must still require explicit permission');
}

$approvedPayload = $payload;
$approvedPayload['permission_codes'] = ['/biz/task/approve'];
if ($permission->invoke($middleware, $request('/biz/task/approve', 'POST'), $approvedPayload) !== true) {
    throw new RuntimeException('explicit task approval permission was not honored');
}

if ($permission->invoke($middleware, $request('/biz/customer/page'), $payload) !== false) {
    throw new RuntimeException('unrelated business read path became public to authenticated users');
}

echo "auth task read bootstrap smoke passed\n";
