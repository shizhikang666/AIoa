<?php

declare(strict_types=1);

namespace app\controller\tenant;

use app\controller\sys\BaseSysController;
use app\service\auth\TokenService;
use app\service\tenant\TenantsService;
use app\support\ApiResponse;
use RuntimeException;
use think\facade\Cache;
use think\Request;
use think\Response;

class TenantsController extends BaseSysController
{
    public function __construct(
        private readonly TenantsService $tenantsService = new TenantsService(),
        private readonly TokenService $tokenService = new TokenService(),
    )
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->detail($this->tenantId($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->add($this->bodyInput($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->edit($this->bodyInput($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        if (!$this->hasSafeMarker($request, 'tenants')) {
            return ApiResponse::fail('需要安全验证', 408, 'tenants');
        }

        return $this->guard(fn () => $this->tenantsService->delete($this->bodyInput($request), $this->authPayload($request)));
    }

    /**
     * @return array<string|int, mixed>
     */
    private function bodyInput(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $raw = '';
        if (method_exists($request, 'getContent')) {
            $raw = trim((string)$request->getContent());
        }
        if ($raw === '' && method_exists($request, 'getInput')) {
            $raw = trim((string)$request->getInput());
        }
        if ($raw !== '') {
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            throw new RuntimeException('invalid json body', 400);
        }

        return $request->param();
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function hasSafeMarker(Request $request, string $mark): bool
    {
        $token = $this->tokenService->bearerFromRequest($request);
        if ($token === null || $token === '') {
            return false;
        }

        return (bool)Cache::get('oa:auth:safe:' . $mark . ':' . hash('sha256', $token));
    }

    private function tenantId(Request $request): string
    {
        $value = trim((string)$request->param('tenantId', $request->param('id', '')));
        if ($value === '') {
            return $this->requiredString($request, 'id');
        }

        return $value;
    }
}
