<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\DeliveryRecordService;
use app\support\ApiResponse;
use think\Request;
use think\Response;

class DeliveryRecordController extends BaseSysController
{
    public function __construct(private readonly DeliveryRecordService $deliveryRecordService = new DeliveryRecordService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->deliveryRecordService->page($request->get(), $this->authPayload($request)));
    }

    public function exportOtherCompanyRecordsList(Request $request): Response
    {
        return $this->guard(fn () => $this->deliveryRecordService->exportOtherCompanyRecordsList($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->deliveryRecordService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function add(): Response
    {
        return $this->deferredWrite('delivery record add');
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail($operation . ' is deferred', 400, [
            'operation' => $operation,
        ]);
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
