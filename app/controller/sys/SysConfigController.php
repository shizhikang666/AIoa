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

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->sysConfigService->edit(
            $this->body($request),
            $request->middleware('auth_payload', [])
        ));
    }

    private function body(Request $request): array
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
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
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
