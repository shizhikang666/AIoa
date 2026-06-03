<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\SettlementAccountPaymentService;
use think\Request;
use think\Response;

class SettlementAccountPaymentController extends BaseSysController
{
    public function __construct(private readonly SettlementAccountPaymentService $paymentService = new SettlementAccountPaymentService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentService->list($request->get(), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
