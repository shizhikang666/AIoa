<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizLeaveApplicationService;
use think\Request;
use think\Response;

class BizLeaveApplicationController extends BaseSysController
{
    public function __construct(private readonly BizLeaveApplicationService $leaveApplicationService = new BizLeaveApplicationService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->leaveApplicationService->page($request->get(), $this->authPayload($request)));
    }

    public function myPage(Request $request): Response
    {
        return $this->guard(fn () => $this->leaveApplicationService->myPage($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->leaveApplicationService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
