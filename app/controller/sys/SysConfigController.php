<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\sys\SysConfigService;
use think\Request;
use think\Response;

class SysConfigController extends BaseSysController
{
    public function __construct(private readonly SysConfigService $sysConfigService = new SysConfigService())
    {
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->sysConfigService->detail($this->tenantId($request)));
    }

    private function tenantId(Request $request): ?string
    {
        $payload = $request->middleware('auth_payload', []);
        if (!is_array($payload)) {
            return null;
        }

        $tenantId = (string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '');

        return $tenantId === '' ? null : $tenantId;
    }
}
