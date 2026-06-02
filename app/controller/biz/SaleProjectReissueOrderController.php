<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectBillingService;
use think\Request;
use think\Response;

class SaleProjectReissueOrderController extends BaseSysController
{
    public function __construct(private readonly SaleProjectBillingService $billingService = new SaleProjectBillingService())
    {
    }

    public function listQuery(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->reissueOrderListQuery($this->requiredString($request, 'projectId'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
