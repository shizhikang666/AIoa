<?php

namespace app\middleware;

use app\service\auth\TokenService;
use app\support\ApiResponse;
use Closure;
use think\Request;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tokenService = new TokenService();
        $payload = $tokenService->getPayload($tokenService->bearerFromRequest($request));

        if ($payload === null) {
            return ApiResponse::fail('未登录或登录已过期', 401);
        }

        if (!$this->hasRoutePermission($request, $payload)) {
            return ApiResponse::fail('permission denied', 403);
        }

        $request->withMiddleware([
            'auth_payload' => $payload,
        ]);

        return $next($request);
    }

    private function hasRoutePermission(Request $request, array $payload): bool
    {
        if ($this->hasBuiltInRole($payload)) {
            return true;
        }

        $path = $this->normalizedPath($request);
        if ($path === '') {
            return true;
        }

        if ($this->isAuthenticatedBootstrapPath($path, $request)) {
            return true;
        }

        return in_array($path, $this->stringList($payload['permission_codes'] ?? []), true);
    }

    private function isAuthenticatedBootstrapPath(string $path, Request $request): bool
    {
        if (str_starts_with($path, '/sys/usercenter/') || str_starts_with($path, '/sys/index/')) {
            return true;
        }

        if (str_starts_with($path, '/dev/file/upload') && strtoupper((string)$request->method()) === 'POST') {
            return true;
        }

        $method = strtoupper((string)$request->method());
        if ($method === 'POST' && in_array($path, [
            '/biz/process/variable',
            '/biz/process/filelist',
            '/biz/process/project/product-item-relation/list',
        ], true)) {
            return true;
        }

        if ($method !== 'GET') {
            return false;
        }

        return in_array($path, [
            '/sys/sysconfig/detail',
            '/dev/dict/tree',
            '/biz/task/count',
            '/biz/task/list',
            '/biz/task/page',
            '/biz/task/history/page',
            '/biz/task/runtime/activity/detail',
            '/biz/process/page',
            '/biz/process/detail',
        ], true);
    }

    private function hasBuiltInRole(array $payload): bool
    {
        foreach ($this->stringList($payload['role_codes'] ?? []) as $roleCode) {
            if (in_array(strtolower($roleCode), ['superadmin', 'tenantadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizedPath(Request $request): string
    {
        $path = trim((string)$request->pathinfo());
        if ($path === '') {
            $uri = (string)$request->server('REQUEST_URI', '');
            $path = (string)parse_url($uri, PHP_URL_PATH);
        }

        $path = '/' . trim($path, '/');
        $path = preg_replace('#/+#', '/', strtolower($path)) ?: '';
        foreach (['/backend', '/api', '/index.php'] as $prefix) {
            if ($path === $prefix) {
                return '';
            }
            if (str_starts_with($path, $prefix . '/')) {
                $path = substr($path, strlen($prefix));
            }
        }

        return rtrim($path, '/') ?: '';
    }

    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(static fn (mixed $item): string => strtolower(trim((string)$item)), $value))));
    }
}
