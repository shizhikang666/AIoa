<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SalesProjectFieldChangeLogService;
use think\Request;
use think\Response;

class SalesProjectFieldChangeLogController extends BaseSysController
{
    public function __construct(private readonly SalesProjectFieldChangeLogService $changeLogService = new SalesProjectFieldChangeLogService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->changeLogService->page($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->changeLogService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
