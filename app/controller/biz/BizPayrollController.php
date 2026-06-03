<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizPayrollService;
use think\Request;
use think\Response;

class BizPayrollController extends BaseSysController
{
    public function __construct(private readonly BizPayrollService $payrollService = new BizPayrollService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->page($request->get(), $this->authPayload($request)));
    }

    public function myPage(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->myPage($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
