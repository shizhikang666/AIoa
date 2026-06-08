<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\LogService;
use RuntimeException;
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

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->logService->delete($this->bodyCategory($request), $this->tenantId($request)));
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

    private function bodyCategory(Request $request): string
    {
        $input = $request->post();
        if ($input === []) {
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
                    $input = $decoded;
                }
            }
        }

        $category = trim((string)($input['category'] ?? $input['CATEGORY'] ?? $request->param('category', '')));
        if ($category === '') {
            throw new RuntimeException('missing category', 400);
        }

        return $category;
    }
}
