<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SaleProjectBillingService;
use think\Request;
use think\Response;

class SaleProjectRateController extends BaseSysController
{
    public function __construct(private readonly SaleProjectBillingService $billingService = new SaleProjectBillingService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->ratePage($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateList($this->requiredString($request, 'projectId'), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->billingService->rateDetail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
