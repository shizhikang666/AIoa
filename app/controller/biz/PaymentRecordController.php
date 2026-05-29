<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\PaymentRecordService;
use think\Request;
use think\Response;

class PaymentRecordController extends BaseSysController
{
    public function __construct(private readonly PaymentRecordService $paymentRecordService = new PaymentRecordService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentRecordService->page($request->get(), $this->authPayload($request)));
    }

    public function listDetails(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentRecordService->listDetails($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentRecordService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->paymentRecordService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
