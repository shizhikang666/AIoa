<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizUserVacationService;
use think\Request;
use think\Response;

class BizUserVacationController extends BaseSysController
{
    public function __construct(private readonly BizUserVacationService $vacationService = new BizUserVacationService())
    {
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->vacationService->detail(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
