<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\LogService;
use think\Request;
use think\Response;

class LogController extends BaseSysController
{
    public function __construct(private readonly LogService $logService = new LogService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->page($request->get(), $this->tenantId($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->detail($this->requiredString($request, 'id'), $this->tenantId($request)));
    }

    public function visLineChartData(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->visLineChartData($this->tenantId($request)));
    }

    public function visPieChartData(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->visPieChartData($this->tenantId($request)));
    }

    public function opBarChartData(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->opBarChartData($this->tenantId($request)));
    }

    public function opPieChartData(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->opPieChartData($this->tenantId($request)));
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
